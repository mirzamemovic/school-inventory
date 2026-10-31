<?php

use App\Enums\ActorType;
use App\Models\AssetEvent;
use App\Models\User;
use App\Services\AdminAccounts;
use App\Support\EventAction;
use App\Support\EventSentence;
use App\Support\PasswordRecheck;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
 * Manage → Users. Every account is an admin; there are no roles. What is
 * tested here is that the guardrails hold on the server, whatever the page
 * shows: a fresh password re-entry, never seeing a password, once-only links,
 * deactivation instead of deletion, and a log of every change.
 */

const GOOD_PASSWORD = 'olive tractor hums quietly';

beforeEach(function () {
    // The breached-password check never leaves the machine.
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);

    $this->admin = User::factory()->create([
        'name' => 'Drita Berisha',
        'email' => 'drita@school.example',
        'password' => Hash::make(GOOD_PASSWORD),
    ]);
});

/** Signed in, with the password typed in the last few minutes. */
function asConfirmedAdmin(?User $user = null): TestCase
{
    return test()->actingAs($user ?? test()->admin)
        ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()]);
}

/** The once-only link from the page an admin was sent to. */
function linkFrom(TestResponse $response): string
{
    return session('setup_link')['url'];
}

function tokenFrom(string $link): string
{
    return basename(parse_url($link, PHP_URL_PATH));
}

// --- Who can reach it -------------------------------------------------------

it('sends someone who is not signed in to the sign-in page from every user route', function (string $method, string $route) {
    $other = User::factory()->create();

    $this->call($method, route($route, ['user' => $other]))->assertRedirect(route('login'));
})->with([
    ['GET', 'admin.users.index'],
    ['GET', 'admin.users.create'],
    ['POST', 'admin.users.store'],
    ['GET', 'admin.users.show'],
    ['GET', 'admin.users.edit'],
    ['PUT', 'admin.users.update'],
    ['POST', 'admin.users.password'],
    ['POST', 'admin.users.deactivate'],
    ['POST', 'admin.users.reactivate'],
    ['GET', 'admin.account.password'],
    ['PUT', 'admin.account.password.update'],
    ['GET', 'admin.password.confirm'],
]);

it('asks a signed-in admin for their password again before any user route, and does nothing until then', function (string $method, string $route) {
    $other = User::factory()->create(['name' => 'Arben Krasniqi', 'email' => 'arben@school.example']);

    $this->actingAs($this->admin)
        ->call($method, route($route, ['user' => $other]), ['name' => 'Changed', 'email' => 'changed@school.example'])
        ->assertRedirect(route('admin.password.confirm'));

    $other->refresh();
    expect($other->name)->toBe('Arben Krasniqi')
        ->and($other->isDeactivated())->toBeFalse()
        ->and($other->isWaitingForPassword())->toBeFalse()
        ->and(User::query()->count())->toBe(2)
        ->and(AssetEvent::query()->where('action', 'like', 'user.%')->count())->toBe(0);
})->with([
    ['GET', 'admin.users.index'],
    ['GET', 'admin.users.create'],
    ['POST', 'admin.users.store'],
    ['GET', 'admin.users.show'],
    ['GET', 'admin.users.edit'],
    ['PUT', 'admin.users.update'],
    ['POST', 'admin.users.password'],
    ['POST', 'admin.users.deactivate'],
    ['POST', 'admin.users.reactivate'],
]);

it('asks again once ten minutes have passed', function () {
    asConfirmedAdmin()->get(route('admin.users.index'))->assertOk();

    $this->travel(11)->minutes();

    $this->get(route('admin.users.index'))->assertRedirect(route('admin.password.confirm'));
});

it('lets an admin through after typing their password again, back to where they were going', function () {
    $this->actingAs($this->admin)->get(route('admin.users.index'))->assertRedirect(route('admin.password.confirm'));

    $this->post(route('admin.password.confirm.store'), ['password' => GOOD_PASSWORD])
        ->assertRedirect(route('admin.users.index'));

    $this->get(route('admin.users.index'))->assertOk()->assertSee('Drita Berisha');
});

it('refuses a wrong password at the re-entry, and stops guessing after five', function () {
    $this->actingAs($this->admin);

    foreach (range(1, PasswordRecheck::ATTEMPTS_PER_MINUTE) as $attempt) {
        $this->post(route('admin.password.confirm.store'), ['password' => 'wrong'])
            ->assertSessionHasErrors('password');
    }

    // Locked for the minute: even the right one is refused.
    $this->post(route('admin.password.confirm.store'), ['password' => GOOD_PASSWORD])
        ->assertSessionHasErrors('password');

    $this->get(route('admin.users.index'))->assertRedirect(route('admin.password.confirm'));
});

// --- Never a password -------------------------------------------------------

it('never shows a password, a hash or a remember token on any user page', function () {
    $other = User::factory()->create();

    foreach ([route('admin.users.index'), route('admin.users.show', $other), route('admin.users.edit', $other)] as $url) {
        $html = asConfirmedAdmin()->get($url)->assertOk()->getContent();

        expect($html)->not->toContain($other->password)
            ->and($html)->not->toContain($this->admin->password)
            ->and($html)->not->toContain('$2y$')
            ->and($html)->not->toContain((string) $other->remember_token);
    }
});

it('keeps the password and remember token out of anything the model turns into', function () {
    expect($this->admin->toArray())->not->toHaveKeys(['password', 'remember_token'])
        ->and($this->admin->toJson())->not->toContain('$2y$');
});

it('has no route that could show a password', function () {
    $uris = collect(app('router')->getRoutes()->getRoutes())->map->uri();

    expect($uris->filter(fn ($uri) => preg_match('/(show|view|reveal)[-_]?password/i', $uri)))->toBeEmpty();
});

// --- Adding a user ----------------------------------------------------------

it('adds a user who can only sign in after choosing a password with a once-only link', function () {
    $response = asConfirmedAdmin()->post(route('admin.users.store'), [
        'name' => 'Arben Krasniqi',
        'email' => '  Arben@School.Example ',
    ]);

    $user = User::query()->where('email', 'arben@school.example')->firstOrFail();
    $response->assertRedirect(route('admin.users.show', $user));

    expect($user->isWaitingForPassword())->toBeTrue()
        ->and($user->canSignIn())->toBeFalse();

    // The link is on the page this once…
    $link = linkFrom($response);
    $this->get(route('admin.users.show', $user))->assertSee($link)->assertSee(__('users.link.shown_once'));

    // …and never again: only its hash is kept.
    $this->get(route('admin.users.show', $user))->assertDontSee($link)->assertSee(__('users.waiting', ['name' => 'Arben Krasniqi']));

    $stored = DB::table('password_reset_tokens')->where('email', 'arben@school.example')->value('token');
    expect($stored)->not->toBe(tokenFrom($link))
        ->and(Hash::check(tokenFrom($link), $stored))->toBeTrue();
});

it('never lets the request choose a password, a state or a role for a new user', function () {
    asConfirmedAdmin()->post(route('admin.users.store'), [
        'name' => 'Arben Krasniqi',
        'email' => 'arben@school.example',
        'password' => GOOD_PASSWORD,
        'password_set_at' => now()->toDateTimeString(),
        'deactivated_at' => null,
        'remember_token' => 'chosen-by-attacker',
        'last_login_at' => now()->toDateTimeString(),
        'role' => 'superadmin',
        'is_admin' => 1,
        'id' => 999,
    ]);

    $user = User::query()->where('email', 'arben@school.example')->firstOrFail();

    expect($user->id)->not->toBe(999)
        ->and(Hash::check(GOOD_PASSWORD, $user->password))->toBeFalse()
        ->and($user->password_set_at)->toBeNull()
        ->and($user->remember_token)->not->toBe('chosen-by-attacker')
        ->and($user->last_login_at)->toBeNull();

    auth()->logout();
    $this->post('/login', ['email' => 'arben@school.example', 'password' => GOOD_PASSWORD])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('refuses a second user with the same email address, whatever its capitals', function () {
    asConfirmedAdmin()->post(route('admin.users.store'), ['name' => 'Someone', 'email' => 'DRITA@school.example'])
        ->assertSessionHasErrors(['email' => __('users.email_taken')]);

    expect(User::query()->count())->toBe(1);
});

it('allows at most three active users, and hides the button when full', function () {
    User::factory()->count(2)->create();
    User::factory()->deactivated()->create();

    asConfirmedAdmin()->get(route('admin.users.index'))
        ->assertDontSee(route('admin.users.create'))
        ->assertSee(__('users.full', ['max' => User::MAX_ACTIVE]));

    $this->get(route('admin.users.create'))->assertForbidden();
    $this->post(route('admin.users.store'), ['name' => 'Fourth', 'email' => 'fourth@school.example'])->assertForbidden();

    expect(User::query()->count())->toBe(4);
});

it('logs who added which user, and never the link or a password', function () {
    $response = asConfirmedAdmin()->post(route('admin.users.store'), ['name' => 'Arben Krasniqi', 'email' => 'arben@school.example']);
    $user = User::query()->where('email', 'arben@school.example')->firstOrFail();

    $event = AssetEvent::query()->where('action', EventAction::USER_CREATED)->sole();

    expect($event->subject_type)->toBe('user')
        ->and($event->subject_id)->toBe($user->id)
        ->and($event->actor_type)->toBe(ActorType::Admin)
        ->and($event->actor_id)->toBe($this->admin->id)
        ->and($event->meta)->toBeMeta(['account' => 'Arben Krasniqi', 'email' => 'arben@school.example'])
        ->and(json_encode($event->getAttributes()))->not->toContain(tokenFrom(linkFrom($response)))
        ->and(EventSentence::for($event))->toBe('Drita Berisha added Arben Krasniqi as a user');
});

// --- The once-only link ------------------------------------------------------

it('lets the person choose their password with the link, once, and then sign in with it', function () {
    $response = asConfirmedAdmin()->post(route('admin.users.store'), ['name' => 'Arben Krasniqi', 'email' => 'arben@school.example']);
    $link = linkFrom($response);
    $user = User::query()->where('email', 'arben@school.example')->firstOrFail();

    auth()->logout();
    $this->flushSession();

    // Opening it, as a messaging app's preview would, uses nothing up.
    $this->get($link)->assertOk()->assertSee('arben@school.example');
    $this->get($link)->assertOk();

    $this->post($link, ['password' => 'cedar lantern drifts slowly', 'password_confirmation' => 'cedar lantern drifts slowly'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', __('users.setup.done'));

    $user->refresh();
    expect($user->canSignIn())->toBeTrue()
        ->and(Hash::check('cedar lantern drifts slowly', $user->password))->toBeTrue()
        ->and(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();

    // Used up.
    $this->get($link)->assertStatus(410);
    $this->post($link, ['password' => 'another phrase of words', 'password_confirmation' => 'another phrase of words'])->assertStatus(410);

    $this->post('/login', ['email' => 'arben@school.example', 'password' => 'cedar lantern drifts slowly'])
        ->assertRedirect(route('admin.home'));
    $this->assertAuthenticatedAs($user);

    $event = AssetEvent::query()->where('action', EventAction::USER_PASSWORD_SET)->sole();
    expect($event->actor_id)->toBe($user->id)
        ->and(json_encode($event->getAttributes()))->not->toContain('cedar lantern');
});

it('holds a password chosen with the link to the same rule as any other', function () {
    $response = asConfirmedAdmin()->post(route('admin.users.store'), ['name' => 'Arben Krasniqi', 'email' => 'arben@school.example']);
    $link = linkFrom($response);

    $this->post($link, ['password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHasErrors(['password' => __('validation.min.string', ['attribute' => __('users.password.attribute'), 'min' => 16])]);
    $this->post($link, ['password' => GOOD_PASSWORD, 'password_confirmation' => 'something else entirely'])
        ->assertSessionHasErrors(['password' => __('users.password.mismatch')]);

    // A refused password does not use the link up.
    $this->get($link)->assertOk();
});

it('stops a link working after an hour', function () {
    $link = linkFrom(asConfirmedAdmin()->post(route('admin.users.store'), ['name' => 'Arben Krasniqi', 'email' => 'arben@school.example']));

    $this->travel(61)->minutes();

    $this->get($link)->assertStatus(410)->assertSee(__('users.setup.unavailable_heading'));
});

it('answers the same for a made-up link and a made-up account', function () {
    $user = User::factory()->waitingForPassword()->create();

    $this->get(route('account.setup', ['user' => $user->id, 'token' => str_repeat('a', 64)]))->assertStatus(410);
    $this->get(route('account.setup', ['user' => 9999, 'token' => str_repeat('a', 64)]))->assertStatus(410);
});

it('withdraws the old link when a new one is made', function () {
    $first = linkFrom(asConfirmedAdmin()->post(route('admin.users.store'), ['name' => 'Arben Krasniqi', 'email' => 'arben@school.example']));
    $user = User::query()->where('email', 'arben@school.example')->firstOrFail();

    $response = $this->post(route('admin.users.password', $user));
    $second = linkFrom($response);

    // Nobody's password was reset: they never had one.
    $response->assertSessionHas('status', __('users.new_link_done', ['name' => 'Arben Krasniqi']));

    $this->get($first)->assertStatus(410);
    $this->get($second)->assertOk();
});

// --- Resetting a password ----------------------------------------------------

it('resets a password: the old one stops working at once and the person is signed out everywhere', function () {
    config(['session.driver' => 'database']);
    $other = User::factory()->create(['email' => 'arben@school.example', 'password' => Hash::make(GOOD_PASSWORD), 'remember_token' => 'old-token']);
    DB::table('sessions')->insert([
        'id' => 'arben-phone', 'user_id' => $other->id, 'ip_address' => '203.0.113.9',
        'user_agent' => 'x', 'payload' => '', 'last_activity' => now()->getTimestamp(),
    ]);

    $response = asConfirmedAdmin()->post(route('admin.users.password', $other));
    $response->assertRedirect(route('admin.users.show', $other));
    $link = linkFrom($response);

    $other->refresh();
    expect(Hash::check(GOOD_PASSWORD, $other->password))->toBeFalse()
        ->and($other->isWaitingForPassword())->toBeTrue()
        ->and($other->remember_token)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $other->id)->exists())->toBeFalse();

    $this->get(route('admin.users.show', $other))->assertSee($link);

    $event = AssetEvent::query()->where('action', EventAction::USER_PASSWORD_RESET)->sole();
    expect(EventSentence::for($event))->toBe("Drita Berisha reset the password for {$other->name}");
});

it('turns away someone whose password was reset while they were signed in', function () {
    $other = User::factory()->create();

    $this->actingAs($other)->get(route('admin.home'))->assertOk();

    $other->forceFill(['password_set_at' => null])->save();

    $this->actingAs($other)->get(route('admin.home'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => __('auth.password_was_reset')]);

    $this->assertGuest();
});

it('does not let an admin reset their own password from the Users page', function () {
    asConfirmedAdmin()->get(route('admin.users.show', $this->admin))->assertDontSee(route('admin.users.password', $this->admin));

    asConfirmedAdmin()->post(route('admin.users.password', $this->admin))->assertForbidden();

    expect($this->admin->fresh()->canSignIn())->toBeTrue();
});

it('does not reset the password of a deactivated user', function () {
    $other = User::factory()->deactivated()->create();

    asConfirmedAdmin()->post(route('admin.users.password', $other))->assertForbidden();

    expect($other->fresh()->isWaitingForPassword())->toBeFalse();
});

// --- Deactivating and reactivating -------------------------------------------

it('deactivates a user: they are signed out everywhere and cannot sign in, but nothing is deleted', function () {
    config(['session.driver' => 'database']);
    $other = User::factory()->create(['email' => 'arben@school.example', 'password' => Hash::make(GOOD_PASSWORD)]);
    DB::table('sessions')->insert([
        'id' => 'arben-laptop', 'user_id' => $other->id, 'ip_address' => '203.0.113.9',
        'user_agent' => 'x', 'payload' => '', 'last_activity' => now()->getTimestamp(),
    ]);

    asConfirmedAdmin()->post(route('admin.users.deactivate', $other))->assertRedirect(route('admin.users.show', $other));

    $other->refresh();
    expect($other->isDeactivated())->toBeTrue()
        ->and(User::query()->whereKey($other->id)->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $other->id)->exists())->toBeFalse();

    auth()->logout();

    // The right password gets a plain explanation; a wrong one learns nothing.
    $this->post('/login', ['email' => 'arben@school.example', 'password' => GOOD_PASSWORD])
        ->assertSessionHasErrors(['email' => __('auth.deactivated')]);
    $this->post('/login', ['email' => 'arben@school.example', 'password' => 'a wrong guess entirely'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);
    $this->assertGuest();

    $event = AssetEvent::query()->where('action', EventAction::USER_DEACTIVATED)->sole();
    expect(EventSentence::for($event))->toBe("Drita Berisha deactivated {$other->name}");
});

it('turns away a deactivated user who was already signed in, on their next click', function () {
    $other = User::factory()->create();

    $this->actingAs($other)->get(route('admin.assets.index'))->assertOk();

    $other->forceFill(['deactivated_at' => now()])->save();

    $this->actingAs($other)->get(route('admin.assets.index'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => __('auth.deactivated')]);

    $this->assertGuest();
});

it('tells an htmx request from a deactivated user to go to the sign-in page', function () {
    $other = User::factory()->deactivated()->create();

    $this->actingAs($other)
        ->get(route('admin.search', ['q' => 'pc']), ['HX-Request' => 'true'])
        ->assertHeader('HX-Redirect', route('login'));
});

it('does not let an admin deactivate themselves, so the last admin can never be switched off', function () {
    asConfirmedAdmin()->get(route('admin.users.show', $this->admin))->assertDontSee(route('admin.users.deactivate', $this->admin));

    asConfirmedAdmin()->post(route('admin.users.deactivate', $this->admin))->assertForbidden();

    expect($this->admin->fresh()->isDeactivated())->toBeFalse();
});

it('does not let a switched-off admin act, even with a session from before', function () {
    $other = User::factory()->create();

    // Arben deactivates Drita. Drita's next request, even one already on its
    // way, cannot switch Arben off in return.
    asConfirmedAdmin($other)->post(route('admin.users.deactivate', $this->admin))->assertRedirect();

    // As a real request would: the account is read fresh from the database.
    asConfirmedAdmin($this->admin->fresh())->post(route('admin.users.deactivate', $other))->assertRedirect(route('login'));

    expect($other->fresh()->isDeactivated())->toBeFalse();
});

it('re-checks the rules under a lock, so a stale page cannot undo them', function () {
    // The policy is asked again inside the transaction with fresh rows: an
    // admin deactivated a moment ago (whose request already passed the
    // middleware) is refused by the service itself.
    $other = User::factory()->create();
    $stale = clone $this->admin;
    User::query()->whereKey($this->admin->id)->update(['deactivated_at' => now()]);

    expect(fn () => app(AdminAccounts::class)->deactivate($stale, $other))
        ->toThrow(AuthorizationException::class);

    expect($other->fresh()->isDeactivated())->toBeFalse();
});

it('reactivates a user with the password they had, within the limit', function () {
    $other = User::factory()->deactivated()->create(['email' => 'arben@school.example', 'password' => Hash::make(GOOD_PASSWORD)]);

    asConfirmedAdmin()->post(route('admin.users.reactivate', $other))->assertRedirect(route('admin.users.show', $other));

    expect($other->fresh()->isDeactivated())->toBeFalse();
    expect(AssetEvent::query()->where('action', EventAction::USER_REACTIVATED)->count())->toBe(1);

    auth()->logout();
    $this->post('/login', ['email' => 'arben@school.example', 'password' => GOOD_PASSWORD])->assertRedirect(route('admin.home'));
});

it('does not reactivate a user when three are already active', function () {
    User::factory()->count(2)->create();
    $other = User::factory()->deactivated()->create();

    asConfirmedAdmin()->get(route('admin.users.show', $other))->assertDontSee(__('users.reactivate_help'));
    asConfirmedAdmin()->post(route('admin.users.reactivate', $other))->assertForbidden();

    expect($other->fresh()->isDeactivated())->toBeTrue();
});

it('offers no way to delete a user', function () {
    $other = User::factory()->create();

    asConfirmedAdmin()->delete('/admin/users/'.$other->id)->assertStatus(405);

    expect(User::query()->whereKey($other->id)->exists())->toBeTrue();
});

// --- Editing ----------------------------------------------------------------

it('edits a name and email, and logs what changed from what', function () {
    $other = User::factory()->create(['name' => 'Arben Krasniqi', 'email' => 'arben@school.example']);

    asConfirmedAdmin()->put(route('admin.users.update', $other), [
        'name' => 'Arben Krasniqi',
        'email' => 'a.krasniqi@school.example',
        'password' => 'sneaked in',
        'deactivated_at' => now()->toDateTimeString(),
    ])->assertRedirect(route('admin.users.show', $other));

    $other->refresh();
    expect($other->email)->toBe('a.krasniqi@school.example')
        ->and($other->isDeactivated())->toBeFalse()
        ->and(Hash::check('sneaked in', $other->password))->toBeFalse();

    $event = AssetEvent::query()->where('action', EventAction::USER_EDITED)->sole();
    expect($event->meta['email'])->toBeMeta(['from' => 'arben@school.example', 'to' => 'a.krasniqi@school.example'])
        ->and(EventSentence::for($event))->toBe('Drita Berisha changed the details of Arben Krasniqi from arben@school.example to a.krasniqi@school.example');
});

it('writes nothing to the log when nothing changed', function () {
    $other = User::factory()->create(['name' => 'Arben Krasniqi', 'email' => 'arben@school.example']);

    asConfirmedAdmin()->put(route('admin.users.update', $other), ['name' => 'Arben Krasniqi', 'email' => 'arben@school.example']);

    expect(AssetEvent::query()->where('action', EventAction::USER_EDITED)->count())->toBe(0);
});

it('refuses an email address another user already signs in with', function () {
    $other = User::factory()->create();

    asConfirmedAdmin()->put(route('admin.users.update', $other), ['name' => $other->name, 'email' => 'Drita@School.example'])
        ->assertSessionHasErrors(['email' => __('users.email_taken')]);
});

it('kills a waiting link when the email address it was made for changes', function () {
    $link = linkFrom(asConfirmedAdmin()->post(route('admin.users.store'), ['name' => 'Arben Krasniqi', 'email' => 'arben@school.example']));
    $user = User::query()->where('email', 'arben@school.example')->firstOrFail();

    $this->put(route('admin.users.update', $user), ['name' => 'Arben Krasniqi', 'email' => 'arben.k@school.example']);

    $this->get($link)->assertStatus(410);
    expect(DB::table('password_reset_tokens')->count())->toBe(0);
});

// --- Change my password -------------------------------------------------------

it('changes my own password, signing out my other devices but not this one', function () {
    config(['session.driver' => 'database']);
    DB::table('sessions')->insert([
        'id' => 'drita-phone', 'user_id' => $this->admin->id, 'ip_address' => '203.0.113.9',
        'user_agent' => 'x', 'payload' => '', 'last_activity' => now()->getTimestamp(),
    ]);

    $this->actingAs($this->admin)->put(route('admin.account.password.update'), [
        'current_password' => GOOD_PASSWORD,
        'password' => 'cedar lantern drifts slowly',
        'password_confirmation' => 'cedar lantern drifts slowly',
    ])->assertRedirect(route('admin.manage'))->assertSessionHas('status', __('users.password.changed'));

    $this->assertAuthenticatedAs($this->admin);
    expect(Hash::check('cedar lantern drifts slowly', $this->admin->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'drita-phone')->exists())->toBeFalse();

    $event = AssetEvent::query()->where('action', EventAction::USER_PASSWORD_CHANGED)->sole();
    expect(json_encode($event->getAttributes()))->not->toContain('cedar lantern')
        ->and(json_encode($event->getAttributes()))->not->toContain(GOOD_PASSWORD);
});

it('needs the current password to change it, and limits guessing', function () {
    $this->actingAs($this->admin);

    foreach (range(1, PasswordRecheck::ATTEMPTS_PER_MINUTE) as $attempt) {
        $this->put(route('admin.account.password.update'), [
            'current_password' => 'wrong',
            'password' => 'cedar lantern drifts slowly',
            'password_confirmation' => 'cedar lantern drifts slowly',
        ])->assertSessionHasErrors('current_password');
    }

    $this->put(route('admin.account.password.update'), [
        'current_password' => GOOD_PASSWORD,
        'password' => 'cedar lantern drifts slowly',
        'password_confirmation' => 'cedar lantern drifts slowly',
    ])->assertSessionHasErrors('current_password');

    expect(Hash::check(GOOD_PASSWORD, $this->admin->fresh()->password))->toBeTrue();
});

it('holds a changed password to the same rule, and refuses the same one again', function () {
    $this->actingAs($this->admin);

    $this->put(route('admin.account.password.update'), [
        'current_password' => GOOD_PASSWORD, 'password' => 'short', 'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    $this->put(route('admin.account.password.update'), [
        'current_password' => GOOD_PASSWORD, 'password' => GOOD_PASSWORD, 'password_confirmation' => GOOD_PASSWORD,
    ])->assertSessionHasErrors(['password' => __('users.password.unchanged')]);

    expect(Hash::check(GOOD_PASSWORD, $this->admin->fresh()->password))->toBeTrue();
});

// --- Signing in ----------------------------------------------------------------

it('records when someone last signed in', function () {
    expect($this->admin->last_login_at)->toBeNull();

    $this->post('/login', ['email' => 'drita@school.example', 'password' => GOOD_PASSWORD]);

    expect($this->admin->fresh()->last_login_at)->not->toBeNull();
});

it('does not let an account waiting for its link sign in', function () {
    // Even if someone somehow knew the stored password: waiting means waiting.
    User::factory()->create([
        'email' => 'arben@school.example',
        'password' => Hash::make(GOOD_PASSWORD),
        'password_set_at' => null,
    ]);

    $this->post('/login', ['email' => 'arben@school.example', 'password' => GOOD_PASSWORD])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

// --- The list -----------------------------------------------------------------

it('lists every user with their state, the date they were added, and when they last signed in', function () {
    User::factory()->create(['name' => 'Arben Krasniqi', 'last_login_at' => now()->subHours(2)]);
    User::factory()->waitingForPassword()->create(['name' => 'Besa Gashi']);
    User::factory()->deactivated()->create(['name' => 'Valon Hoxha']);

    asConfirmedAdmin()->get(route('admin.users.index'))
        ->assertOk()
        ->assertSeeInOrder(['Arben Krasniqi', 'Besa Gashi', 'Drita Berisha', 'Valon Hoxha'])
        ->assertSee(__('users.status.active'))
        ->assertSee(__('users.status.waiting'))
        ->assertSee(__('users.status.deactivated'))
        ->assertSee(__('users.last_signed_in', ['time' => '2 hours ago']))
        ->assertSee(__('users.never_signed_in'))
        ->assertSee(__('users.you'));
});
