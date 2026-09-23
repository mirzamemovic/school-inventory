<?php

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\RoomType;
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

it('sends an admin who scans a blank label straight into intake', function () {
    $blank = Asset::factory()->blank()->create();

    $this->actingAs($this->admin)
        ->get('/a/'.$blank->asset_tag)
        ->assertRedirect(route('admin.intake.edit', $blank));
});

it('sends an admin who scans a filled-in label to the full record instead', function () {
    $asset = Asset::factory()->create();

    $this->actingAs($this->admin)
        ->get('/a/'.$asset->asset_tag)
        ->assertRedirect(route('admin.assets.show', $asset));
});

it('asks for four fields and no more', function () {
    $blank = Asset::factory()->blank()->create();

    $response = $this->actingAs($this->admin)->get(route('admin.intake.edit', $blank))->assertOk();

    $response->assertSee(__('assets.labels.name'))
        ->assertSee(__('assets.labels.category'))
        ->assertSee(__('assets.labels.room'))
        ->assertSee(__('assets.labels.condition'))
        ->assertDontSee(__('assets.labels.purchase_price'))
        ->assertDontSee(__('assets.labels.supplier'))
        ->assertDontSee(__('assets.labels.serial_number'));
});

it('fills in a blank label and returns to the scan box, never to a list', function () {
    $blank = Asset::factory()->blank()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.intake.update', $blank), [
            'name' => 'Epson EB-X41',
            'category_id' => $this->category->id,
            'room_id' => $this->room->id,
            'condition' => AssetCondition::Good->value,
        ])
        ->assertRedirect(route('admin.intake.start'));

    $blank->refresh();

    expect($blank->name)->toBe('Epson EB-X41')
        ->and($blank->status)->toBe(AssetStatus::InUse)
        ->and($blank->room_id)->toBe($this->room->id);
});

it('keeps the pre-printed tag, because the sticker is already on the wall', function () {
    $blank = Asset::factory()->blank()->create();
    $tag = $blank->asset_tag;

    $this->actingAs($this->admin)->put(route('admin.intake.update', $blank), [
        'name' => 'Epson EB-X41',
        'category_id' => $this->category->id,
        'room_id' => $this->room->id,
        'condition' => AssetCondition::Good->value,
    ]);

    // The blank prefix itself is covered where blanks are generated; what
    // matters here is that filling the label in does not renumber it.
    expect($blank->fresh()->asset_tag)->toBe($tag);
});

it('puts something labelled in a storage room into storage rather than into use', function () {
    $store = Room::factory()->create(['name' => 'Store 1', 'type' => RoomType::Storage]);
    $blank = Asset::factory()->blank()->create();

    $this->actingAs($this->admin)->put(route('admin.intake.update', $blank), [
        'name' => 'Spare projector',
        'category_id' => $this->category->id,
        'room_id' => $store->id,
        'condition' => AssetCondition::Good->value,
    ]);

    expect($blank->fresh()->status)->toBe(AssetStatus::InStorage);
});

it('turns on the inspection schedule for a compliance category without asking', function () {
    $fireSafety = Category::factory()->inspected(12)->create(['prefix' => 'FIRE']);
    $blank = Asset::factory()->blank()->create();

    $this->actingAs($this->admin)->put(route('admin.intake.update', $blank), [
        'name' => 'Fire extinguisher, corridor',
        'category_id' => $fireSafety->id,
        'room_id' => $this->room->id,
        'condition' => AssetCondition::Good->value,
    ]);

    $blank->refresh();

    expect($blank->needs_inspection)->toBeTrue()
        ->and($blank->inspection_interval_months)->toBe(12);
});

it('writes the intake to the history', function () {
    $blank = Asset::factory()->blank()->create();

    $this->actingAs($this->admin)->put(route('admin.intake.update', $blank), [
        'name' => 'Epson EB-X41',
        'category_id' => $this->category->id,
        'room_id' => $this->room->id,
        'condition' => AssetCondition::Good->value,
    ]);

    $event = AssetEvent::query()->where('action', EventAction::ASSET_CREATED)->firstOrFail();

    expect($event->meta['via'])->toBe('bulk intake')
        ->and($event->actor_name)->toBe($this->admin->name);
});

it('opens a label typed in by hand when the QR will not read', function () {
    $blank = Asset::factory()->blank()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.intake.find'), ['asset_tag' => strtolower($blank->asset_tag)])
        ->assertRedirect(route('admin.intake.edit', $blank));
});

it('says plainly when a typed code does not exist', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.intake.find'), ['asset_tag' => 'INV-GEN-9999'])
        ->assertSessionHasErrors('asset_tag');
});

it('refuses to overwrite a label that someone has already filled in', function () {
    $asset = Asset::factory()->create(['name' => 'Already done']);

    $this->actingAs($this->admin)
        ->get(route('admin.intake.edit', $asset))
        ->assertRedirect(route('admin.assets.show', $asset));
});

it('counts how many blank labels are still waiting', function () {
    Asset::factory()->blank()->count(3)->create();
    Asset::factory()->count(2)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.intake.start'))
        ->assertOk()
        ->assertSee(trans_choice('intake.remaining', 3, ['count' => 3]));
});
