<?php

use App\Enums\LicenseModel;
use App\Enums\LicenseType;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\License;
use App\Models\User;
use App\Services\NeedsAttention;
use App\Support\EventAction;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

function licensePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Microsoft 365',
        'vendor' => 'Microsoft',
        'type' => LicenseType::Software->value,
        'licence_model' => LicenseModel::Subscription->value,
        'seats_total' => 25,
        'seats_used' => 18,
        'expires_at' => now()->addYear()->toDateString(),
        'renewal_cost' => 240,
        'account_reference' => 'it@school.example',
    ], $overrides);
}

/* -- shape ------------------------------------------------------------- */

it('keeps licenses out of the assets table', function () {
    License::factory()->create();

    expect(Asset::query()->count())->toBe(0)
        ->and(Schema::hasTable('licenses'))->toBeTrue();
});

it('gives a license none of the things that belong to a physical object', function () {
    foreach (['room_id', 'condition', 'status', 'asset_tag', 'photo_path'] as $column) {
        expect(Schema::hasColumn('licenses', $column))->toBeFalse();
    }
});

it('builds no seat assignment table and no license-to-asset join table', function () {
    foreach (['license_user', 'license_seats', 'asset_license', 'license_assets', 'license_renewals'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
});

it('links a license to at most one machine, and lets go when that machine is deleted', function () {
    $asset = Asset::factory()->create();
    $license = License::factory()->create(['asset_id' => $asset->id]);

    expect($license->asset->is($asset))->toBeTrue();

    $asset->delete();

    expect($license->fresh()->asset_id)->toBeNull()
        ->and(License::query()->count())->toBe(1);
});

it('keeps using the one audit log rather than a second one', function () {
    $license = License::factory()->create();
    $this->actingAs($this->admin)->put(route('admin.licenses.update', $license), licensePayload());

    expect(AssetEvent::query()->where('subject_type', 'licence')->exists())->toBeTrue()
        ->and(Schema::hasTable('asset_events'))->toBeTrue()
        ->and(Schema::hasTable('license_events'))->toBeFalse();
});

/* -- access ------------------------------------------------------------ */

it('keeps every license screen behind a password', function () {
    $license = License::factory()->create();

    $this->get(route('admin.licenses.index'))->assertRedirect(route('login'));
    $this->get(route('admin.licenses.show', $license))->assertRedirect(route('login'));
    $this->post(route('admin.licenses.store'), licensePayload())->assertRedirect(route('login'));
});

it('lives under Manage and adds no sixth navigation item', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.manage'))->assertOk();

    $response->assertSee(__('manage.licenses'));

    // The top navigation is still exactly the five items from section 8 —
    // counted inside the <nav> only, since the page's own cards are list
    // items too.
    preg_match('#<nav[^>]*>(.*?)</nav>#s', $response->getContent(), $nav);

    expect(substr_count($nav[1], '<li>'))->toBe(5)
        ->and($nav[1])->not->toContain(__('licenses.title'));
});

/* -- writing ----------------------------------------------------------- */

it('adds a license and logs it', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.licenses.store'), licensePayload())
        ->assertRedirect();

    $license = License::query()->firstOrFail();

    expect($license->name)->toBe('Microsoft 365')
        ->and($license->seats_total)->toBe(25);

    $event = AssetEvent::query()->where('action', EventAction::LICENCE_CREATED)->firstOrFail();

    expect($event->subject_type)->toBe('licence')
        ->and($event->subject_id)->toBe($license->id)
        ->and($event->actor_name)->toBe($this->admin->name);
});

it('accepts a license with no expiry date at all', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.licenses.store'), licensePayload([
            'licence_model' => LicenseModel::Perpetual->value,
            'expires_at' => null,
        ]))
        ->assertRedirect();

    $license = License::query()->firstOrFail();

    expect($license->expires_at)->toBeNull()
        ->and($license->isExpired())->toBeFalse();
});

it('refuses a license with no name or an unknown kind', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.licenses.store'), licensePayload(['name' => '', 'type' => 'spaceship']))
        ->assertSessionHasErrors(['name', 'type']);
});

it('refuses more seats used than the school owns', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.licenses.store'), licensePayload(['seats_total' => 5, 'seats_used' => 9]))
        ->assertSessionHasErrors('seats_used');
});

it('logs an edit with what actually changed', function () {
    $license = License::factory()->create(['name' => 'Microsoft 365', 'seats_total' => 25]);

    $this->actingAs($this->admin)
        ->put(route('admin.licenses.update', $license), licensePayload(['seats_total' => 30]))
        ->assertRedirect();

    $event = AssetEvent::query()->where('action', EventAction::LICENCE_EDITED)->firstOrFail();

    expect($event->meta['seats_total']['from'])->toBe(25)
        ->and($event->meta['seats_total']['to'])->toBe(30);
});

it('writes nothing to the log when an edit changes nothing', function () {
    $license = License::factory()->create();

    $this->actingAs($this->admin)->put(route('admin.licenses.update', $license), [
        'name' => $license->name,
        'vendor' => $license->vendor,
        'type' => $license->type->value,
        'licence_model' => $license->licence_model->value,
        'seats_total' => $license->seats_total,
        'seats_used' => $license->seats_used,
        'expires_at' => $license->expires_at->toDateString(),
        'renewal_cost' => $license->renewal_cost,
        'account_reference' => $license->account_reference,
    ]);

    expect(AssetEvent::query()->where('action', EventAction::LICENCE_EDITED)->count())->toBe(0);
});

/* -- renewing ---------------------------------------------------------- */

it('renews a license and records both dates in the log', function () {
    $license = License::factory()->create(['expires_at' => '2026-09-01']);

    $this->actingAs($this->admin)
        ->post(route('admin.licenses.renew', $license), [
            'expires_at' => '2027-09-01',
            'renewal_cost' => 260,
        ])
        ->assertRedirect();

    expect($license->fresh()->expires_at->toDateString())->toBe('2027-09-01')
        ->and((float) $license->fresh()->renewal_cost)->toBe(260.0);

    $event = AssetEvent::query()->where('action', EventAction::LICENCE_RENEWED)->firstOrFail();

    expect($event->meta['expires_at']['from'])->toBe('2026-09-01')
        ->and($event->meta['expires_at']['to'])->toBe('2027-09-01');
});

it('renews without touching the cost when none is given', function () {
    $license = License::factory()->create(['expires_at' => '2026-09-01', 'renewal_cost' => 240]);

    $this->actingAs($this->admin)->post(route('admin.licenses.renew', $license), [
        'expires_at' => '2027-09-01',
    ]);

    expect((float) $license->fresh()->renewal_cost)->toBe(240.0);
});

it('can give a perpetual license an expiry date by renewing it', function () {
    $license = License::factory()->perpetual()->create();

    $this->actingAs($this->admin)->post(route('admin.licenses.renew', $license), [
        'expires_at' => '2027-09-01',
    ]);

    $event = AssetEvent::query()->where('action', EventAction::LICENCE_RENEWED)->firstOrFail();

    expect($event->meta['expires_at']['from'])->toBeNull()
        ->and($event->meta['expires_at']['to'])->toBe('2027-09-01');
});

it('needs a date to renew', function () {
    $license = License::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.licenses.renew', $license), ['expires_at' => ''])
        ->assertSessionHasErrors('expires_at');
});

it('shows the renewal history on the license page', function () {
    $license = License::factory()->create(['expires_at' => '2026-09-01']);

    $this->actingAs($this->admin)->post(route('admin.licenses.renew', $license), [
        'expires_at' => '2027-09-01',
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.licenses.show', $license))
        ->assertOk()
        ->assertSee($this->admin->name.' renewed it from 2026-09-01 to 2027-09-01');
});

/* -- deleting ---------------------------------------------------------- */

it('removes a license and keeps the record that it was removed', function () {
    $license = License::factory()->create(['name' => 'Zoom Pro']);

    $this->actingAs($this->admin)
        ->delete(route('admin.licenses.destroy', $license))
        ->assertRedirect(route('admin.licenses.index'));

    expect(License::query()->count())->toBe(0);

    $event = AssetEvent::query()->where('action', EventAction::LICENCE_DELETED)->firstOrFail();

    expect($event->meta['name'])->toBe('Zoom Pro');
});

/* -- the list ---------------------------------------------------------- */

it('shows perpetual licenses as never expiring, with no warning colour', function () {
    License::factory()->perpetual()->create(['name' => 'Windows OEM']);

    $response = $this->actingAs($this->admin)->get(route('admin.licenses.index'))->assertOk();

    $response->assertSee('Windows OEM')->assertSee(__('licenses.never_expires'));

    expect($response->getContent())->not->toContain('bg-status-broken-soft');
});

it('marks a license expiring inside the window amber and an expired one red', function () {
    License::factory()->expiringInDays(30)->create(['name' => 'ESET Endpoint']);

    expect($this->actingAs($this->admin)->get(route('admin.licenses.index'))->getContent())
        ->toContain('bg-status-attention-soft');

    License::query()->delete();
    License::factory()->expired()->create(['name' => 'Adobe Acrobat']);

    expect($this->actingAs($this->admin)->get(route('admin.licenses.index'))->getContent())
        ->toContain('bg-status-broken-soft');
});

it('words the warning by whether somebody has to act or money will move', function () {
    $manual = License::factory()->expiringInDays(20)->create();
    $auto = License::factory()->expiringInDays(20)->autoRenewing()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.licenses.show', $manual))
        ->assertSee(__('licenses.warning.manual'));

    $this->actingAs($this->admin)
        ->get(route('admin.licenses.show', $auto))
        ->assertSee(__('licenses.warning.auto'));
});

it('finds a license by vendor or by the account it belongs to', function () {
    License::factory()->create(['name' => 'Microsoft 365', 'vendor' => 'Microsoft', 'account_reference' => 'it@school.example']);
    License::factory()->create(['name' => 'Zoom Pro', 'vendor' => 'Zoom', 'account_reference' => 'office@school.example']);

    $this->actingAs($this->admin)
        ->get(route('admin.licenses.index', ['q' => 'it@school']))
        ->assertOk()
        ->assertSee('Microsoft 365')
        ->assertDontSee('Zoom Pro');
});

/* -- home -------------------------------------------------------------- */

it('puts a license running out inside 60 days on the home list', function () {
    License::factory()->expiringInDays(30)->create(['name' => 'ESET Endpoint']);

    expect(app(NeedsAttention::class)->rows()->first()['label'])->toContain('ESET Endpoint');
});

it('never puts a perpetual license on the home list', function () {
    License::factory()->perpetual()->count(3)->create();

    expect(app(NeedsAttention::class)->rows())->toBeEmpty();
});

it('leaves a license with months left off the home list', function () {
    License::factory()->expiringInDays(200)->create();

    expect(app(NeedsAttention::class)->rows())->toBeEmpty();
});
