<?php

use App\Models\User;
use Database\Seeders\DemoAdminSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

const DEMO_ADMIN_ENV = ['DEMO_ADMIN_EMAIL', 'DEMO_ADMIN_PASSWORD'];

function demoAdminEnv(array $values): void
{
    foreach ($values as $name => $value) {
        $_ENV[$name] = $_SERVER[$name] = $value;
    }
}

beforeEach(function () {
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
});

afterEach(function () {
    foreach (DEMO_ADMIN_ENV as $name) {
        unset($_ENV[$name], $_SERVER[$name]);
    }
});

it('changes nothing when the container starts again', function () {
    demoAdminEnv(['DEMO_ADMIN_EMAIL' => 'director@example.com', 'DEMO_ADMIN_PASSWORD' => 'correct horse battery staple']);

    $this->seed(DemoAdminSeeder::class);
    $this->seed(DemoAdminSeeder::class);

    expect(User::query()->count())->toBe(1);
});

it('makes the admin account from the host settings', function () {
    demoAdminEnv(['DEMO_ADMIN_EMAIL' => ' Director@Example.com ', 'DEMO_ADMIN_PASSWORD' => 'correct horse battery staple']);

    $this->seed(DemoAdminSeeder::class);

    $user = User::query()->sole();

    expect($user->email)->toBe('director@example.com')
        ->and(Hash::check('correct horse battery staple', $user->password))->toBeTrue();
});

it('makes no account from a weak password, and still finishes', function () {
    demoAdminEnv(['DEMO_ADMIN_EMAIL' => 'director@example.com', 'DEMO_ADMIN_PASSWORD' => 'short']);

    $this->seed(DemoAdminSeeder::class);

    expect(User::query()->count())->toBe(0);
});

it('makes no account when none is configured, so there is never a default password', function () {
    $this->seed(DemoAdminSeeder::class);

    expect(User::query()->count())->toBe(0);
});
