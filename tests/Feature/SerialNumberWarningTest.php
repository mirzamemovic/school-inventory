<?php

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\FundingSource;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Room;
use App\Models\User;
use App\Services\SerialNumberCheck;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->room = Room::factory()->create(['name' => 'Room 12']);
    $this->category = Category::factory()->create(['prefix' => 'PC']);
});

function serialPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Dell OptiPlex 3070',
        'category_id' => test()->category->id,
        'room_id' => test()->room->id,
        'status' => AssetStatus::InUse->value,
        'condition' => AssetCondition::Good->value,
        'funding_source' => FundingSource::Budget->value,
    ], $overrides);
}

/* -- it warns, it never blocks ----------------------------------------- */

it('saves an asset with a blank serial and says nothing about it', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.assets.store'), serialPayload(['serial_number' => '']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Asset::query()->count())->toBe(1)
        ->and(Asset::query()->first()->serial_number)->toBeNull();

    $this->actingAs($this->admin)
        ->get(route('admin.assets.create'))
        ->assertOk()
        ->assertDontSee(__('assets.serial_in_use'));
});

it('saves a duplicate serial anyway, and warns', function () {
    // A block here would leave somebody holding a laptop unable to record it.
    $existing = Asset::factory()->create([
        'name' => 'Dell OptiPlex 3070',
        'serial_number' => 'SN-4471-XQ',
        'room_id' => $this->room->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.assets.store'), serialPayload(['serial_number' => 'SN-4471-XQ']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Asset::query()->where('serial_number', 'SN-4471-XQ')->count())->toBe(2);

    // And the warning names the one that was already there, with a link.
    $this->actingAs($this->admin)
        ->get(route('admin.assets.serial-check', ['serial_number' => 'SN-4471-XQ']))
        ->assertOk()
        ->assertSee(__('assets.serial_in_use'))
        ->assertSee($existing->displayName())
        ->assertSee($existing->asset_tag)
        ->assertSee(route('admin.assets.show', $existing), escape: false);
});

it('does not warn an asset about its own serial number', function () {
    $asset = Asset::factory()->create(['serial_number' => 'SN-4471-XQ']);

    $this->actingAs($this->admin)
        ->get(route('admin.assets.serial-check', [
            'serial_number' => 'SN-4471-XQ',
            'except' => $asset->id,
        ]))
        ->assertOk()
        ->assertDontSee(__('assets.serial_in_use'));

    // Nor when simply opening its edit form.
    $this->actingAs($this->admin)
        ->get(route('admin.assets.edit', $asset))
        ->assertOk()
        ->assertDontSee(__('assets.serial_in_use'));
});

it('still warns on the edit form when another asset shares the serial', function () {
    $first = Asset::factory()->create(['name' => 'First PC', 'serial_number' => 'SN-4471-XQ']);
    $second = Asset::factory()->create(['name' => 'Second PC', 'serial_number' => 'SN-4471-XQ']);

    $this->actingAs($this->admin)
        ->get(route('admin.assets.edit', $second))
        ->assertOk()
        ->assertSee(__('assets.serial_in_use'))
        ->assertSee('First PC');

    expect($first->fresh())->not->toBeNull();
});

it('lets an asset keep its own serial when saved again', function () {
    $asset = Asset::factory()->create([
        'serial_number' => 'SN-4471-XQ',
        'room_id' => $this->room->id,
        'category_id' => $this->category->id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.assets.update', $asset), serialPayload(['serial_number' => 'SN-4471-XQ']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($asset->fresh()->serial_number)->toBe('SN-4471-XQ');
});

/* -- what counts as the same serial ------------------------------------ */

it('treats a serial as the same whatever the case or the stray spaces', function () {
    Asset::factory()->create(['name' => 'Original PC', 'serial_number' => 'SN-4471-XQ']);

    foreach (['sn-4471-xq', '  SN-4471-XQ  ', 'Sn-4471-Xq'] as $typed) {
        $this->actingAs($this->admin)
            ->get(route('admin.assets.serial-check', ['serial_number' => $typed]))
            ->assertOk()
            ->assertSee('Original PC');
    }
});

it('says nothing at all for a blank or whitespace-only serial', function () {
    Asset::factory()->create(['serial_number' => 'SN-4471-XQ']);

    foreach (['', '   '] as $typed) {
        $this->actingAs($this->admin)
            ->get(route('admin.assets.serial-check', ['serial_number' => $typed]))
            ->assertOk()
            ->assertDontSee(__('assets.serial_in_use'));
    }
});

it('never matches one blank serial against another', function () {
    Asset::factory()->count(3)->create(['serial_number' => null]);

    expect(app(SerialNumberCheck::class)->duplicateOf(null))->toBeNull()
        ->and(app(SerialNumberCheck::class)->duplicateOf(''))->toBeNull();
});

it('counts the rest when a serial turns up more than twice', function () {
    Asset::factory()->count(3)->create(['serial_number' => 'SN-REUSED']);

    $this->actingAs($this->admin)
        ->get(route('admin.assets.serial-check', ['serial_number' => 'SN-REUSED']))
        ->assertOk()
        ->assertSee(trans_choice('assets.serial_in_use_others', 2, ['count' => 2]));
});

/* -- the loop and the shape of things ---------------------------------- */

it('leaves bulk intake exactly as it was', function () {
    // Four fields and one button. Nothing was added to the loop.
    $blank = Asset::factory()->blank()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.intake.edit', $blank))
        ->assertOk()
        ->assertDontSee(__('assets.labels.serial_number'))
        ->assertDontSee('serial-number-warning');
});

it('interrupts nothing on the way to the next label', function () {
    $blank = Asset::factory()->blank()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.intake.update', $blank), [
            'name' => 'Epson EB-X41',
            'category_id' => $this->category->id,
            'room_id' => $this->room->id,
            'condition' => AssetCondition::Good->value,
        ])
        ->assertRedirect(route('admin.intake.start'))
        ->assertSessionHasNoErrors();
});

it('keeps the check behind the password', function () {
    Asset::factory()->create(['name' => 'Secret PC', 'serial_number' => 'SN-4471-XQ']);

    $this->get(route('admin.assets.serial-check', ['serial_number' => 'SN-4471-XQ']))
        ->assertRedirect(route('login'))
        ->assertDontSee('Secret PC');
});

it('adds no column and no index to the schema', function () {
    expect(Schema::getColumnListing('assets'))->not->toContain('serial_number_hash')
        ->and(Schema::hasColumn('assets', 'serial_number'))->toBeTrue();

    // The serial stays plain, nullable and non-unique.
    $first = Asset::factory()->create(['serial_number' => 'SN-SAME']);
    $second = Asset::factory()->create(['serial_number' => 'SN-SAME']);

    expect($first->serial_number)->toBe($second->serial_number);
});

it('leaves the four form groups where they were', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.assets.create'))->assertOk();

    foreach (['identity', 'purchase', 'location', 'condition'] as $group) {
        $response->assertSee(__('assets.groups.'.$group));
    }

    expect(substr_count($response->getContent(), '<details'))->toBe(4);
});

it('still renders the serial number field itself on both forms', function () {
    // Guard against the failure that hid here once: a Blade component tag
    // whose attribute value contained a nested quote was dropped silently,
    // taking the whole input with it while every other assertion still passed.
    $asset = Asset::factory()->create(['serial_number' => 'SN-4471-XQ']);

    foreach ([route('admin.assets.create'), route('admin.assets.edit', $asset)] as $url) {
        $response = $this->actingAs($this->admin)->get($url)->assertOk();

        $response->assertSee('name="serial_number"', escape: false)
            ->assertSee('id="serial-number-warning"', escape: false)
            ->assertSee(route('admin.assets.serial-check'), escape: false);
    }
});

it('carries the asset being edited in the check url, so it skips itself', function () {
    $asset = Asset::factory()->create(['serial_number' => 'SN-4471-XQ']);

    $this->actingAs($this->admin)
        ->get(route('admin.assets.edit', $asset))
        ->assertOk()
        ->assertSee('except='.$asset->getKey(), escape: false);
});

it('keeps every other field in the identity group', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.assets.create'))->assertOk();

    foreach (['name', 'category_id', 'make', 'model', 'serial_number', 'official_number'] as $field) {
        $response->assertSee('name="'.$field.'"', escape: false);
    }
});
