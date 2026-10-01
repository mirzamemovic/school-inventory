<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AdminAccounts;
use App\Support\AdminPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * There is no public registration route (spec section 3.1). Accounts are made
 * by another admin in Manage → Users; this is for the very first account, and
 * for when nobody can sign in at all. It is not held to the limit of active
 * accounts: whoever has the server's command line already has everything.
 */
class CreateAdminUser extends Command
{
    protected $signature = 'user:create {name} {email}';

    protected $description = 'Create an admin account';

    public function handle(AdminAccounts $accounts): int
    {
        $name = $this->argument('name');
        $email = Str::lower(trim($this->argument('email')));

        if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            $this->error("An account already exists for {$email}. Use user:password to change its password.");

            return self::FAILURE;
        }

        $this->line(__('auth.password_hint', ['length' => AdminPassword::MIN_LENGTH]));

        $password = $this->secret('Choose a password');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => AdminPassword::rules($email)],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $accounts->createFromConsole(['name' => $name, 'email' => $email], $password);

        $this->info("Created the account for {$email}.");

        return self::SUCCESS;
    }
}
