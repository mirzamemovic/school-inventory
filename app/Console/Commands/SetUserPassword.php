<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AdminPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * The whole password reset story. Resetting by email needs email, and email is
 * not configured; with two accounts this is the right amount of machinery
 * (spec section 3.1).
 *
 * The usual reason to run it is "someone may know my password", so it also
 * signs that account out everywhere: every open session and every "keep me
 * signed in" cookie stops working, or a new password would lock nobody out.
 */
class SetUserPassword extends Command
{
    protected $signature = 'user:password {email}';

    protected $description = 'Set the password for an admin account and sign it out everywhere';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("No account found for {$email}.");

            return self::FAILURE;
        }

        $this->line(__('auth.password_hint', ['length' => AdminPassword::MIN_LENGTH]));

        $password = $this->secret('Choose a new password');

        $validator = Validator::make(
            ['password' => $password],
            ['password' => AdminPassword::rules($email)],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user->forceFill([
            'password' => Hash::make($password),
            'remember_token' => null,
        ])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        $this->info("Password changed for {$email}. It is now signed out everywhere.");

        return self::SUCCESS;
    }
}
