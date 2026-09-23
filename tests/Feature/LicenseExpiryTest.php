<?php

use App\Models\License;

/*
| The specification calls a null expiry "the most likely bug in this feature"
| and asks for it to be tested. Everything in this file is that one rule seen
| from a different angle: a perpetual licence has no expiry date, and no expiry
| date is not an expiry date long ago.
*/

it('never calls a perpetual licence expired', function () {
    expect(License::factory()->perpetual()->create()->isExpired())->toBeFalse();
});

it('never calls a perpetual licence due for renewal', function () {
    expect(License::factory()->perpetual()->create()->isExpiringSoon(60))->toBeFalse();
});

it('reports no countdown at all for a perpetual licence', function () {
    $license = License::factory()->perpetual()->create();

    expect($license->neverExpires())->toBeTrue()
        ->and($license->daysUntilExpiry())->toBeNull()
        ->and($license->expires_at)->toBeNull();
});

it('calls a licence expired only once its date has actually passed', function () {
    expect(License::factory()->expired(1)->create()->isExpired())->toBeTrue()
        ->and(License::factory()->expiringInDays(1)->create()->isExpired())->toBeFalse();
});

it('treats the expiry day itself as not yet expired', function () {
    $today = License::factory()->create(['expires_at' => now()->toDateString()]);

    expect($today->isExpired())->toBeFalse()
        ->and($today->isExpiringSoon(60))->toBeTrue();
});

it('leaves perpetual licences out of the expiring list entirely', function () {
    License::factory()->perpetual()->count(3)->create();
    $soon = License::factory()->expiringInDays(30)->create();

    expect(License::query()->expiringWithin(60)->pluck('id')->all())->toBe([$soon->id]);
});

it('counts an already expired licence as needing attention', function () {
    // It has not stopped being a problem just because the date went by.
    $expired = License::factory()->expired(400)->create();

    expect(License::query()->expiringWithin(60)->pluck('id')->all())->toBe([$expired->id]);
});

it('ignores a licence whose renewal is still months away', function () {
    License::factory()->expiringInDays(200)->create();

    expect(License::query()->expiringWithin(60)->count())->toBe(0);
});

it('sorts soonest first and puts perpetual licences last', function () {
    $perpetual = License::factory()->perpetual()->create(['name' => 'Windows OEM']);
    $far = License::factory()->expiringInDays(300)->create(['name' => 'Zoom']);
    $soon = License::factory()->expiringInDays(10)->create(['name' => 'ESET']);
    $expired = License::factory()->expired(5)->create(['name' => 'Adobe']);

    expect(License::query()->byExpiry()->pluck('id')->all())
        ->toBe([$expired->id, $soon->id, $far->id, $perpetual->id]);
});

it('separates the two meanings of an expiring licence', function () {
    // Manual renewal running out stops the service; automatic renewal running
    // out takes money out of the account (spec section 5).
    expect(License::factory()->create(['auto_renew' => false])->warningKey())
        ->toBe('licenses.warning.manual')
        ->and(License::factory()->autoRenewing()->create()->warningKey())
        ->toBe('licenses.warning.auto');
});
