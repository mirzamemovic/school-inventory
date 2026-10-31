<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\AdminAccounts;
use App\Support\AdminPassword;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * The one thing the Docker demo needs on top of the demo data: a way in.
 * docker/start.sh runs this each time the container starts, because the
 * password is only known to whoever started it. Nothing else calls it, and the
 * school's real deployment never does.
 */
class DemoAdminSeeder extends Seeder
{
    public function run(AdminAccounts $accounts): void
    {
        $this->account($accounts);
    }

    /**
     * A public address needs a real password, so there is no default one. A
     * refused password leaves the public pages running: the log says why, and
     * the fix is to set a better one and restart.
     */
    private function account(AdminAccounts $accounts): void
    {
        $email = Str::lower(trim((string) env('DEMO_ADMIN_EMAIL')));
        $password = (string) env('DEMO_ADMIN_PASSWORD');

        if ($email === '' || $password === '') {
            $this->command?->warn('No admin account: DEMO_ADMIN_EMAIL or DEMO_ADMIN_PASSWORD is not set.');

            return;
        }

        if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            return;
        }

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => AdminPassword::rules($email)],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->command?->warn("No admin account: {$error}");
            }

            return;
        }

        $accounts->createFromConsole(['name' => 'Director', 'email' => $email], $password);
    }
}
