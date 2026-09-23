<?php

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\FundingSource;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\Category;
use App\Models\Room;
use App\Models\User;
use App\Support\EventAction;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->room = Room::factory()->create(['name' => 'Room 12']);
    $this->category = Category::factory()->create(['prefix' => 'PRJ']);
});

function assetPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Epson EB-X41',
        'category_id' => test()->category->id,
        'room_id' => test()->room->id,
        'status' => AssetStatus::InUse->value,
        'condition' => AssetCondition::Good->value,
        'funding_source' => FundingSource::Budget->value,
    ], $overrides);
}

it('sends a signed-out visitor to the login screen and back again afterwards', function () {
    $this->get(route('admin.assets.index'))->assertRedirect(route('login'));
});

it('has no registration route at all', function () {
    expect(collect(Route::getRoutes())->contains(fn ($route) => str_contains($route->uri(), 'register')))
        ->toBeFalse();
});

it('assigns a tag when an asset is saved and logs that it was created', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.assets.store'), assetPayload())
        ->assertRedirect();

    $asset = Asset::query()->firstOrFail();

    expect($asset->asset_tag)->toBe('INV-PRJ-0001')
        ->and($asset->tag_sequence)->toBe(1);

    $event = AssetEvent::query()->where('action', EventAction::ASSET_CREATED)->firstOrFail();

    expect($event->subject_id)->toBe($asset->id)
        ->and($event->actor_name)->toBe($this->admin->name);
});

it('requires a room, because every physical object is somewhere', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.assets.store'), assetPayload(['room_id' => null]))
        ->assertSessionHasErrors('room_id');
});

it('refuses an asset with no name or category', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.assets.store'), assetPayload(['name' => '', 'category_id' => null]))
        ->assertSessionHasErrors(['name', 'category_id']);
});

it('ignores a tag submitted with the form, because tags are never user input', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.assets.store'), assetPayload(['asset_tag' => 'INV-HACK-0001']));

    expect(Asset::query()->firstOrFail()->asset_tag)->toBe('INV-PRJ-0001');
});

it('logs a room change as a move rather than a general edit', function () {
    $asset = Asset::factory()->create(['room_id' => $this->room->id, 'category_id' => $this->category->id]);
    $lab = Room::factory()->create(['name' => 'Lab 2']);

    $this->actingAs($this->admin)
        ->put(route('admin.assets.update', $asset), assetPayload([
            'name' => $asset->name,
            'room_id' => $lab->id,
        ]))
        ->assertRedirect();

    $event = AssetEvent::query()->where('action', EventAction::ASSET_MOVED)->firstOrFail();

    expect($event->meta['room']['from'])->toBe('Room 12')
        ->and($event->meta['room']['to'])->toBe('Lab 2');
});

it('logs a status change separately from an edit', function () {
    $asset = Asset::factory()->create(['room_id' => $this->room->id, 'category_id' => $this->category->id]);

    $this->actingAs($this->admin)
        ->put(route('admin.assets.update', $asset), assetPayload([
            'name' => 'Renamed projector',
            'status' => AssetStatus::InStorage->value,
        ]));

    expect(AssetEvent::query()->where('action', EventAction::ASSET_STATUS_CHANGED)->exists())->toBeTrue()
        ->and(AssetEvent::query()->where('action', EventAction::ASSET_EDITED)->exists())->toBeTrue();
});

it('moves an asset from the detail screen and writes it to the history', function () {
    $asset = Asset::factory()->create(['room_id' => $this->room->id]);
    $lab = Room::factory()->create(['name' => 'Lab 2']);

    $this->actingAs($this->admin)
        ->post(route('admin.assets.move', $asset), ['room_id' => $lab->id])
        ->assertRedirect();

    expect($asset->fresh()->room_id)->toBe($lab->id)
        ->and(AssetEvent::query()->where('action', EventAction::ASSET_MOVED)->exists())->toBeTrue();
});

it('retires an asset without deleting it, keeping its history intact', function () {
    $asset = Asset::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.assets.retire', $asset), ['disposal_method' => 'Given to the scrap collection'])
        ->assertRedirect();

    $asset->refresh();

    expect($asset->status)->toBe(AssetStatus::Retired)
        ->and($asset->disposal_date)->not->toBeNull()
        ->and(AssetEvent::query()->where('action', EventAction::ASSET_RETIRED)->exists())->toBeTrue();
});

it('will not let an admin set the blank-label status by hand', function () {
    $asset = Asset::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.assets.status', $asset), ['status' => AssetStatus::Unassigned->value])
        ->assertSessionHasErrors('status');
});

it('shows the asset detail page with its QR code and history', function () {
    $asset = Asset::factory()->create(['room_id' => $this->room->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.assets.show', $asset))
        ->assertOk()
        ->assertSee($asset->asset_tag)
        ->assertSee('<svg', escape: false)
        ->assertSee(__('assets.timeline'));
});

it('finds an asset by the tag printed next to the QR code', function () {
    $asset = Asset::factory()->create(['name' => 'Findable projector']);

    $this->actingAs($this->admin)
        ->get(route('admin.assets.index', ['q' => $asset->asset_tag]))
        ->assertOk()
        ->assertSee('Findable projector');
});

it('writes the timeline on the asset page as sentences', function () {
    $asset = Asset::factory()->create(['room_id' => $this->room->id, 'category_id' => $this->category->id]);
    $lab = Room::factory()->create(['name' => 'Lab 2']);

    $this->actingAs($this->admin)->put(route('admin.assets.update', $asset), assetPayload([
        'name' => $asset->name,
        'room_id' => $lab->id,
    ]));

    $this->actingAs($this->admin)
        ->get(route('admin.assets.show', $asset))
        ->assertOk()
        ->assertSee($this->admin->name.' moved it from Room 12 to Lab 2');
});
