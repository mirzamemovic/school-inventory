<?php

namespace App\Services;

use App\Models\User;
use App\Support\Actor;
use App\Support\EventAction;
use Carbon\CarbonInterface;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Everything that changes an account that can sign in. Manage → Users and the
 * two artisan commands both come through here, so the rules and the audit log
 * cannot drift apart.
 *
 * Nobody ever learns another person's password. A new or reset account gets a
 * random password nobody knows, and a once-only link with which the person
 * chooses their own. The link is Laravel's own password-reset token: stored
 * only as a hash, gone once used, dead after an hour, and replaced (old one
 * withdrawn) whenever a new one is made.
 *
 * Each change runs in a transaction that first locks the users table and asks
 * the policy again. With two admins, "each switches the other off at the same
 * moment" is the one race worth closing: it would leave nobody able to sign in
 * on a host with no command line.
 */
class AdminAccounts
{
    public function __construct(private readonly AuditLogger $log) {}

    /**
     * @param  array{name: string, email: string}  $details
     * @return array{0: User, 1: string} the account, and its once-only link
     */
    public function create(User $by, array $details): array
    {
        return DB::transaction(function () use ($by, $details) {
            $by = $this->lockAndRecheck($by, 'create');

            $user = new User($details);
            $user->forceFill([
                'password' => $this->passwordNobodyKnows(),
                'password_set_at' => null,
            ])->save();

            $this->log->record($user, EventAction::USER_CREATED, [
                'account' => $user->name,
                'email' => $user->email,
            ], Actor::admin($by));

            return [$user, $this->makeLink($user)];
        });
    }

    /** @param  array{name: string, email: string}  $details */
    public function update(User $by, User $user, array $details): void
    {
        DB::transaction(function () use ($by, $user, $details) {
            $by = $this->lockAndRecheck($by, 'update', $user);
            $user->refresh();

            $before = $user->only(['name', 'email']);
            $user->fill($details);

            if (! $user->isDirty()) {
                return;
            }

            // A link is filed under the email address. One made for the old
            // address must not linger once the account has moved on.
            if ($user->isDirty('email')) {
                DB::table($this->tokenTable())->where('email', $before['email'])->delete();
            }

            $user->save();

            $this->log->record($user, EventAction::USER_EDITED, [
                'account' => $user->name,
                ...$this->log->changes($before, $user->only(['name', 'email'])),
            ], Actor::admin($by));
        });
    }

    /**
     * The old password stops working at once and every device is signed out:
     * the usual reason for a reset is "someone else may know it".
     *
     * @return string the once-only link
     */
    public function resetPassword(User $by, User $user): string
    {
        return DB::transaction(function () use ($by, $user) {
            $by = $this->lockAndRecheck($by, 'resetPassword', $user);
            $user->refresh();

            $user->forceFill([
                'password' => $this->passwordNobodyKnows(),
                'password_set_at' => null,
            ])->save();

            $this->signOutEverywhere($user);

            $this->log->record($user, EventAction::USER_PASSWORD_RESET, ['account' => $user->name], Actor::admin($by));

            return $this->makeLink($user);
        });
    }

    public function deactivate(User $by, User $user): void
    {
        DB::transaction(function () use ($by, $user) {
            $by = $this->lockAndRecheck($by, 'deactivate', $user);
            $user->refresh();

            $user->forceFill(['deactivated_at' => now()])->save();

            $this->signOutEverywhere($user);
            $this->broker()->deleteToken($user);

            $this->log->record($user, EventAction::USER_DEACTIVATED, ['account' => $user->name], Actor::admin($by));
        });
    }

    /**
     * The account comes back with the password it had. If anyone else may
     * know it, the page suggests a reset as the next step.
     */
    public function reactivate(User $by, User $user): void
    {
        DB::transaction(function () use ($by, $user) {
            $by = $this->lockAndRecheck($by, 'reactivate', $user);
            $user->refresh();

            $user->forceFill(['deactivated_at' => null])->save();

            $this->log->record($user, EventAction::USER_REACTIVATED, ['account' => $user->name], Actor::admin($by));
        });
    }

    /** Whether this once-only link would still let someone choose a password. */
    public function linkWorks(User $user, string $token): bool
    {
        return ! $user->isDeactivated() && $this->broker()->tokenExists($user, $token);
    }

    public function linkExpiresAt(): CarbonInterface
    {
        return now()->addMinutes((int) config('auth.passwords.users.expire'));
    }

    /**
     * The person's side of the link. Returns false if the link has been used,
     * withdrawn or has run out — including by a second tap a moment ago.
     */
    public function choosePassword(User $user, string $token, string $password): bool
    {
        return DB::transaction(function () use ($user, $token, $password) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! $this->linkWorks($user, $token)) {
                return false;
            }

            $this->setPassword($user, $password);

            // They proved they hold the link an admin made for this account.
            $this->log->record($user, EventAction::USER_PASSWORD_SET, ['account' => $user->name], Actor::admin($user));

            return true;
        });
    }

    /**
     * A signed-in admin choosing a new password for themselves. Every other
     * device is signed out; this one stays signed in (the caller gives the
     * session a new id).
     */
    public function changeOwnPassword(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password) {
            $this->setPassword($user, $password);

            $this->log->record($user, EventAction::USER_PASSWORD_CHANGED, ['account' => $user->name], Actor::admin($user));
        });
    }

    /**
     * The emergency route, `php artisan user:password`, for when nobody can
     * sign in at all. Whoever has the server's command line already has
     * everything, so no policy applies; it is logged as the system.
     */
    public function setPasswordFromConsole(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password) {
            $this->setPassword($user, $password);

            $this->log->record($user, EventAction::USER_PASSWORD_RESET, ['account' => $user->name], Actor::system());
        });
    }

    /** @param  array{name: string, email: string}  $details */
    public function createFromConsole(array $details, string $password): User
    {
        return DB::transaction(function () use ($details, $password) {
            $user = new User($details);
            $this->setPassword($user, $password);

            $this->log->record($user, EventAction::USER_CREATED, [
                'account' => $user->name,
                'email' => $user->email,
            ], Actor::system());

            return $user;
        });
    }

    /**
     * Lock every account row for the rest of the transaction, then ask the
     * policy again with what is in the database now rather than what the
     * page was showing. There are at most a handful of rows.
     */
    private function lockAndRecheck(User $by, string $ability, ?User $user = null): User
    {
        $accounts = User::query()->lockForUpdate()->get()->keyBy('id');

        $by = $accounts->get($by->id) ?? $by;

        Gate::forUser($by)->authorize($ability, $user ? [$accounts->get($user->id) ?? $user] : User::class);

        return $by;
    }

    private function setPassword(User $user, string $password): void
    {
        $user->forceFill([
            'password' => Hash::make($password),
            'password_set_at' => now(),
        ])->save();

        $this->signOutEverywhere($user);

        // Any link still out there would let someone overwrite what was just chosen.
        $this->broker()->deleteToken($user);
    }

    private function makeLink(User $user): string
    {
        return URL::route('account.setup', [
            'user' => $user->id,
            'token' => $this->broker()->createToken($user),
        ]);
    }

    /**
     * Every open session and every "keep me signed in" cookie stops working.
     * Sessions live in the database in production (DEPLOYMENT.md); for any
     * other driver, EnsureAccountCanSignIn turns away a switched-off or reset
     * account on its next request, and a password change cannot be got round
     * with an old cookie because the remember token is gone.
     */
    private function signOutEverywhere(User $user): void
    {
        $user->forceFill(['remember_token' => null])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }

    private function passwordNobodyKnows(): string
    {
        return Hash::make(Str::random(64));
    }

    private function broker(): PasswordBroker
    {
        return Password::broker();
    }

    private function tokenTable(): string
    {
        return (string) config('auth.passwords.users.table', 'password_reset_tokens');
    }
}
