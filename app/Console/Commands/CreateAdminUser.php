<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

/**
 * There is no public registration route (spec section 3.1). Accounts are made
 * here or by a seeder, and nowhere else.
 */
class CreateAdminUser extends Command
{
    protected $signature = 'user:create {name} {email}';

    protected $description = 'Create an admin account';

    public function handle(): int
    {
        $name = $this->argument('name');
        $email = $this->argument('email');

        if (User::query()->where('email', $email)->exists()) {
            $this->error("An account already exists for {$email}. Use user:password to change its password.");

            return self::FAILURE;
        }

        $password = $this->secret('Choose a password');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => ['required', Password::min(12)->letters()->numbers()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("Created the account for {$email}.");

        return self::SUCCESS;
    }
}
