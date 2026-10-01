<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AdminAccounts;
use App\Support\AdminPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * The emergency route. Day to day, another admin resets a password in
 * Manage → Users and the person chooses a new one with a once-only link; this
 * is for when nobody can sign in at all (spec section 3.1).
 *
 * The usual reason to run it is "someone may know my password", so it also
 * signs that account out everywhere: every open session and every "keep me
 * signed in" cookie stops working, or a new password would lock nobody out.
 * Any once-only link still out there stops working too.
 */
class SetUserPassword extends Command
{
    protected $signature = 'user:password {email}';

    protected $description = 'Set the password for an admin account and sign it out everywhere';

    public function handle(AdminAccounts $accounts): int
    {
        $email = $this->argument('email');
        $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();

        if (! $user) {
            $this->error("No account found for {$email}.");

            return self::FAILURE;
        }

        $this->line(__('auth.password_hint', ['length' => AdminPassword::MIN_LENGTH]));

        $password = $this->secret('Choose a new password');

        $validator = Validator::make(
            ['password' => $password],
            ['password' => AdminPassword::rules($user->email)],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $accounts->setPasswordFromConsole($user, $password);

        $this->info("Password changed for {$email}. It is now signed out everywhere.");

        if ($user->isDeactivated()) {
            $this->warn('This account is deactivated, so it still cannot sign in. Another admin can reactivate it in Manage → Users.');
        }

        return self::SUCCESS;
    }
}
