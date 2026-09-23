<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * The whole password reset story. Resetting by email needs email, and email is
 * not configured; with two accounts this is the right amount of machinery
 * (spec section 3.1).
 */
class SetUserPassword extends Command
{
    protected $signature = 'user:password {email}';

    protected $description = 'Set the password for an admin account';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("No account found for {$email}.");

            return self::FAILURE;
        }

        $password = $this->secret('Choose a new password');

        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', Password::min(12)->letters()->numbers()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user->forceFill(['password' => Hash::make($password)])->save();

        $this->info("Password changed for {$email}.");

        return self::SUCCESS;
    }
}
