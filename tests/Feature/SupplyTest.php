<?php

use App\Models\AssetEvent;
use App\Models\Room;
use App\Models\Supply;
use App\Models\User;
use App\Support\EventAction;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

it('keeps the supplies screen behind a password', function () {
    $this->get(route('admin.supplies.index'))->assertRedirect(route('login'));
});

it('highlights anything at or below its minimum', function () {
    $low = Supply::factory()->low()->create(['name' => 'A4 paper']);
    $fine = Supply::factory()->create(['name' => 'Board markers']);

    expect($low->isLow())->toBeTrue()
        ->and($fine->isLow())->toBeFalse();

    $this->actingAs($this->admin)
        ->get(route('admin.supplies.index'))
        ->assertOk()
        ->assertSee(__('supplies.low'));
});

it('adjusts a quantity by one tap and logs it', function () {
    $supply = Supply::factory()->create(['quantity' => 20]);

    $this->actingAs($this->admin)
        ->post(route('admin.supplies.adjust', $supply), ['by' => -1])
        ->assertRedirect();

    expect($supply->fresh()->quantity)->toBe(19)
        ->and(AssetEvent::query()->where('action', EventAction::SUPPLY_RECOUNTED)->exists())->toBeTrue();
});

it('answers an htmx tap with just the row that changed', function () {
    $supply = Supply::factory()->create(['quantity' => 20, 'name' => 'A4 paper']);

    $response = $this->actingAs($this->admin)
        ->withHeader('HX-Request', 'true')
        ->post(route('admin.supplies.adjust', $supply), ['by' => 1]);

    $response->assertOk()
        ->assertSee('A4 paper')
        ->assertSee('21')
        ->assertDontSee('<!DOCTYPE html>', escape: false);
});

it('never drops a quantity below zero', function () {
    $supply = Supply::factory()->create(['quantity' => 0]);

    $this->actingAs($this->admin)->post(route('admin.supplies.adjust', $supply), ['by' => -1]);

    expect($supply->fresh()->quantity)->toBe(0);
});

it('shows the cupboard page to anyone with no login', function () {
    $room = Room::factory()->create(['name' => 'Store 1']);
    Supply::factory()->create(['name' => 'A4 paper', 'room_id' => $room->id]);

    $this->get(route('cupboard', $room))
        ->assertOk()
        ->assertSee('A4 paper')
        ->assertSee('Store 1')
        ->assertSee('noindex, nofollow', escape: false);
});

it('lets somebody say they took some', function () {
    $room = Room::factory()->create();
    $supply = Supply::factory()->create(['quantity' => 20, 'room_id' => $room->id]);

    $this->post(route('cupboard.take', [$room, $supply]), ['quantity' => 3])
        ->assertRedirect(route('cupboard', $room));

    expect($supply->fresh()->quantity)->toBe(17);

    $event = AssetEvent::query()->where('action', EventAction::SUPPLY_TAKEN)->firstOrFail();

    expect($event->meta['taken'])->toBe(3)
        ->and($event->subject_type)->toBe('supply')
        ->and($event->device_token)->not->toBeNull();
});

it('overwrites the number outright on a recount', function () {
    // Reconciling what is actually on the shelf is more reliable than catching
    // every withdrawal (spec section 8).
    $room = Room::factory()->create();
    $supply = Supply::factory()->create(['quantity' => 20, 'room_id' => $room->id]);

    $this->post(route('cupboard.recount', [$room, $supply]), ['quantity' => 7])
        ->assertRedirect();

    $supply->refresh();

    expect($supply->quantity)->toBe(7)
        ->and($supply->last_counted_at)->not->toBeNull()
        ->and(AssetEvent::query()->where('action', EventAction::SUPPLY_RECOUNTED)->exists())->toBeTrue();
});

it('will not let a public recount raise the stock', function () {
    // Paper "recounted" to 9999 is never running low again: the one way an
    // anonymous tap could silence a warning. Deliveries go through the office.
    $room = Room::factory()->create();
    $supply = Supply::factory()->create(['quantity' => 4, 'minimum_level' => 5, 'room_id' => $room->id]);

    $this->post(route('cupboard.recount', [$room, $supply]), ['quantity' => 9999])
        ->assertSessionHasErrors(['quantity' => __('supplies.recount_too_high', ['count' => 4])]);

    expect($supply->fresh()->quantity)->toBe(4)
        ->and($supply->fresh()->isLow())->toBeTrue()
        ->and(AssetEvent::query()->where('action', EventAction::SUPPLY_RECOUNTED)->exists())->toBeFalse();
});

it('lets a public recount confirm the number as it stands', function () {
    $room = Room::factory()->create();
    $supply = Supply::factory()->create(['quantity' => 12, 'room_id' => $room->id, 'last_counted_at' => null]);

    $this->post(route('cupboard.recount', [$room, $supply]), ['quantity' => 12])->assertRedirect();

    expect($supply->fresh()->quantity)->toBe(12)
        ->and($supply->fresh()->last_counted_at)->not->toBeNull();
});

it('still lets the office raise the stock when a delivery arrives', function () {
    $supply = Supply::factory()->create(['quantity' => 4]);

    $this->actingAs($this->admin)
        ->post(route('admin.supplies.adjust', $supply), ['by' => 1])
        ->assertRedirect();

    expect($supply->fresh()->quantity)->toBe(5);
});

it('refuses a silly withdrawal', function () {
    $room = Room::factory()->create();
    $supply = Supply::factory()->create(['room_id' => $room->id]);

    $this->post(route('cupboard.take', [$room, $supply]), ['quantity' => 9999])
        ->assertSessionHasErrors('quantity');
});

it('shows only the supplies kept in that cupboard', function () {
    $store = Room::factory()->create();
    $office = Room::factory()->create();
    Supply::factory()->create(['name' => 'A4 paper', 'room_id' => $store->id]);
    Supply::factory()->create(['name' => 'Black toner', 'room_id' => $office->id]);

    $this->get(route('cupboard', $store))
        ->assertOk()
        ->assertSee('A4 paper')
        ->assertDontSee('Black toner');
});
