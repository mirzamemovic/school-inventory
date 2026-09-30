<?php

use App\Http\Requests\LoginRequest;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // The breached-password check asks api.pwnedpasswords.com; the tests
    // never leave the machine. An empty answer means "not found in a breach";
    // a test can set $this->breachList to answer otherwise.
    $this->breachList = '';

    Http::fake(['api.pwnedpasswords.com/*' => fn () => Http::response($this->breachList, 200)]);
});

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
    $user = User::factory()->create(['password' => Hash::make('olive tractor hums quietly')]);

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    // Locked: even the right password is refused until the minute is up.
    $this->post('/login', ['email' => $user->email, 'password' => 'olive tractor hums quietly'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('does not let a stranger lock the director out from everywhere', function () {
    // Wrong guesses are counted per email and address: someone failing on
    // purpose from outside does not lock the director out at school.
    $user = User::factory()->create(['password' => Hash::make('olive tractor hums quietly')]);

    foreach (range(1, 5) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
        ->post('/login', ['email' => $user->email, 'password' => 'olive tractor hums quietly'])
        ->assertRedirect(route('admin.home'));

    $this->assertAuthenticatedAs($user);
});

it('still caps guessing spread across many addresses', function () {
    $user = User::factory()->create(['password' => Hash::make('olive tractor hums quietly')]);

    foreach (range(1, LoginRequest::ATTEMPTS_PER_HOUR_FOR_ACCOUNT) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.($attempt % 250)])
            ->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
        ->post('/login', ['email' => $user->email, 'password' => 'olive tractor hums quietly'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('keeps an admin signed in for two weeks at most, not a year', function () {
    $user = User::factory()->create(['password' => Hash::make('olive tractor hums quietly')]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'olive tractor hums quietly',
        'remember' => '1',
    ]);

    $remember = collect($response->headers->getCookies())
        ->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));

    expect($remember)->not->toBeNull()
        ->and($remember->getExpiresTime())->toBeLessThanOrEqual(now()->addDays(14)->addMinute()->getTimestamp());
});

it('refuses a password found in a known breach', function () {
    $user = User::factory()->create();
    $hash = strtoupper(sha1('correct horse battery staple'));

    $this->breachList = substr($hash, 5).":120394\n";

    $this->artisan('user:password', ['email' => $user->email])
        ->expectsQuestion('Choose a new password', 'correct horse battery staple')
        ->assertFailed();
});

it('refuses a password built on the school name or the email address', function () {
    Setting::put('school_name', 'Gjimnazi Test');
    $user = User::factory()->create(['email' => 'drita.berisha@school.example']);

    $this->artisan('user:password', ['email' => $user->email])
        ->expectsQuestion('Choose a new password', 'GjimnaziTest2026 is secure')
        ->assertFailed();

    $this->artisan('user:password', ['email' => $user->email])
        ->expectsQuestion('Choose a new password', 'my name is drita.berisha ok')
        ->assertFailed();
});

it('signs the account out everywhere when its password is changed', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create(['remember_token' => 'old-remember-token']);
    DB::table('sessions')->insert([
        'id' => 'stolen-session', 'user_id' => $user->id, 'ip_address' => '203.0.113.9',
        'user_agent' => 'x', 'payload' => '', 'last_activity' => now()->getTimestamp(),
    ]);

    $this->artisan('user:password', ['email' => $user->email])
        ->expectsQuestion('Choose a new password', 'olive tractor hums quietly')
        ->assertSuccessful();

    expect($user->fresh()->remember_token)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
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
        ->expectsQuestion('Choose a new password', 'olive tractor hums quietly')
        ->expectsOutput("Password changed for {$user->email}. It is now signed out everywhere.")
        ->assertSuccessful();

    expect(Hash::check('olive tractor hums quietly', $user->fresh()->password))->toBeTrue();
});

it('refuses a weak password from the command line', function () {
    $user = User::factory()->create();

    $this->artisan('user:password', ['email' => $user->email])
        ->expectsQuestion('Choose a new password', 'short')
        ->assertFailed();
});

it('creates an admin account from the command line', function () {
    $this->artisan('user:create', ['name' => 'Drita Berisha', 'email' => 'drita@example.test'])
        ->expectsQuestion('Choose a password', 'olive tractor hums quietly')
        ->assertSuccessful();

    expect(User::query()->where('email', 'drita@example.test')->exists())->toBeTrue();
});
