<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

it('signs an admin in with an email and a password', function () {
    $user = User::factory()->create(['password' => Hash::make('a-long-enough-password-1')]);

    $this->post('/login', ['email' => $user->email, 'password' => 'a-long-enough-password-1'])
        ->assertRedirect(route('admin.home'));

    $this->assertAuthenticatedAs($user);
});

it('sends someone back to the page they were trying to reach', function () {
    $user = User::factory()->create(['password' => Hash::make('a-long-enough-password-1')]);

    $this->get(route('admin.labels.index'))->assertRedirect(route('login'));

    $this->post('/login', ['email' => $user->email, 'password' => 'a-long-enough-password-1'])
        ->assertRedirect(route('admin.labels.index'));
});

it('rejects a wrong password', function () {
    $user = User::factory()->create(['password' => Hash::make('a-long-enough-password-1')]);

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('stops guessing after five attempts on the same email', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    expect(RateLimiter::tooManyAttempts(Str::transliterate(Str::lower($user->email).'|login'), 5))->toBeTrue();

    RateLimiter::clear(Str::transliterate(Str::lower($user->email).'|login'));
});

it('signs an admin out', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('offers no password reset screen, because email is not configured', function () {
    $this->get('/forgot-password')->assertNotFound();
    $this->get('/reset-password')->assertNotFound();
});

it('sets a password from the command line instead', function () {
    $user = User::factory()->create();

    $this->artisan('user:password', ['email' => $user->email])
        ->expectsQuestion('Choose a new password', 'another-long-password-9')
        ->expectsOutput("Password changed for {$user->email}.")
        ->assertSuccessful();

    expect(Hash::check('another-long-password-9', $user->fresh()->password))->toBeTrue();
});

it('refuses a weak password from the command line', function () {
    $user = User::factory()->create();

    $this->artisan('user:password', ['email' => $user->email])
        ->expectsQuestion('Choose a new password', 'short')
        ->assertFailed();
});

it('creates an admin account from the command line', function () {
    $this->artisan('user:create', ['name' => 'Drita Berisha', 'email' => 'drita@example.test'])
        ->expectsQuestion('Choose a password', 'a-long-enough-password-1')
        ->assertSuccessful();

    expect(User::query()->where('email', 'drita@example.test')->exists())->toBeTrue();
});
