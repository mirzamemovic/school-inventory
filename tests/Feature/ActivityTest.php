<?php

use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\BlockedDevice;
use App\Models\Room;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Actor;
use App\Support\EventAction;
use App\Support\EventSentence;

beforeEach(function () {
    $this->admin = User::factory()->create(['name' => 'Drita Berisha']);
});

it('keeps the log behind a password', function () {
    $this->get(route('admin.activity.index'))->assertRedirect(route('login'));
});

it('reads each row as a sentence, not a table of ids', function () {
    $asset = Asset::factory()->create();
    $this->actingAs($this->admin);

    app(AuditLogger::class)->record($asset, EventAction::ASSET_MOVED, [
        'room' => ['from' => 'Lab 2', 'to' => 'Room 14'],
    ]);

    $event = AssetEvent::query()->firstOrFail();

    expect(EventSentence::for($event))->toBe('Drita Berisha moved it from Lab 2 to Room 14');

    $this->get(route('admin.activity.index'))
        ->assertOk()
        ->assertSee('Drita Berisha moved it from Lab 2 to Room 14');
});

it('falls back to a plain sentence for an action with no wording', function () {
    $asset = Asset::factory()->create();
    $event = app(AuditLogger::class)->record($asset, 'something.unheard_of');

    expect(EventSentence::for($event->fresh()))->toBe('The system did something');
});

it('shows newest first', function () {
    $asset = Asset::factory()->create();
    $logger = app(AuditLogger::class);

    $older = $logger->record($asset, EventAction::ASSET_CREATED);
    $this->travel(1)->hours();
    $newer = $logger->record($asset, EventAction::ASSET_RETIRED);

    $events = $this->actingAs($this->admin)
        ->get(route('admin.activity.index'))
        ->viewData('events');

    expect($events->first()->id)->toBe($newer->id)
        ->and($events->last()->id)->toBe($older->id);
});

it('filters by what happened', function () {
    $asset = Asset::factory()->create();
    $logger = app(AuditLogger::class);
    $logger->record($asset, EventAction::ASSET_CREATED);
    $logger->record($asset, EventAction::ASSET_RETIRED);

    $events = $this->actingAs($this->admin)
        ->get(route('admin.activity.index', ['action' => EventAction::ASSET_RETIRED]))
        ->viewData('events');

    expect($events)->toHaveCount(1)
        ->and($events->first()->action)->toBe(EventAction::ASSET_RETIRED);
});

it('filters by who and by date', function () {
    $asset = Asset::factory()->create();
    $logger = app(AuditLogger::class);

    $logger->record($asset, EventAction::ASSET_CREATED, [], new Actor(\App\Enums\ActorType::Staff, null, 'Arben Krasniqi'));
    $logger->record($asset, EventAction::ASSET_EDITED, [], new Actor(\App\Enums\ActorType::Staff, null, 'Someone Else'));

    expect($this->actingAs($this->admin)
        ->get(route('admin.activity.index', ['who' => 'Arben']))
        ->viewData('events'))->toHaveCount(1);

    expect($this->actingAs($this->admin)
        ->get(route('admin.activity.index', ['from' => now()->addDay()->toDateString()]))
        ->viewData('events'))->toHaveCount(0);
});

it('answers an htmx scroll request with rows only', function () {
    $asset = Asset::factory()->create();
    $logger = app(AuditLogger::class);

    foreach (range(1, 60) as $i) {
        $logger->record($asset, EventAction::ASSET_EDITED);
    }

    $this->actingAs($this->admin)
        ->withHeader('HX-Request', 'true')
        ->get(route('admin.activity.index'))
        ->assertOk()
        ->assertDontSee('<!DOCTYPE html>', escape: false)
        ->assertSee('<li', escape: false);
});

it('exports the log as a CSV', function () {
    $asset = Asset::factory()->create();
    $this->actingAs($this->admin);
    app(AuditLogger::class)->record($asset, EventAction::ASSET_MOVED, [
        'room' => ['from' => 'Lab 2', 'to' => 'Room 14'],
    ]);

    $response = $this->get(route('admin.activity.export'));

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();

    expect($csv)->toContain(__('activity.csv.when'))
        ->and($csv)->toContain('Drita Berisha moved it from Lab 2 to Room 14')
        ->and($csv)->toContain('asset.moved');
});

it('blocks a device straight from the log, and lets it back', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.activity.block'), ['device_token' => 'abc123', 'note' => 'Joke reports'])
        ->assertRedirect();

    expect(BlockedDevice::blocks('abc123'))->toBeTrue();

    $device = BlockedDevice::query()->firstOrFail();

    $this->actingAs($this->admin)
        ->delete(route('admin.activity.unblock', $device))
        ->assertRedirect();

    expect(BlockedDevice::blocks('abc123'))->toBeFalse();
});

it('says so plainly when nothing has happened', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.activity.index'))
        ->assertOk()
        ->assertSee(__('activity.empty'));
});

it('produces a room inventory sheet with a line per item and blanks to write on', function () {
    $room = Room::factory()->create(['name' => 'Room 12']);
    $asset = Asset::factory()->create(['room_id' => $room->id, 'name' => 'Epson projector']);
    \App\Models\RoomContent::factory()->create([
        'room_id' => $room->id,
        'item_name' => 'Student desk',
        'quantity' => 28,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.rooms.sheet', $room))
        ->assertOk()
        ->assertSee('Epson projector')
        ->assertSee($asset->asset_tag)
        ->assertSee('Student desk')
        ->assertSee('28')
        ->assertSee(__('rooms.signature'));
});

it('marks a room as counted', function () {
    $room = Room::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.rooms.counted', $room))
        ->assertRedirect();

    expect($room->fresh()->last_counted_at)->not->toBeNull();
});

it('counts furniture per room rather than labelling each chair', function () {
    $room = Room::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.rooms.contents.store', $room), [
            'item_name' => 'Chair',
            'quantity' => 30,
            'condition' => \App\Enums\AssetCondition::Good->value,
        ])
        ->assertRedirect();

    expect($room->contents()->count())->toBe(1)
        ->and($room->contents()->first()->quantity)->toBe(30);
});

it('removes a furniture line when its count drops to zero', function () {
    $room = Room::factory()->create();
    $content = \App\Models\RoomContent::factory()->create(['room_id' => $room->id]);

    $this->actingAs($this->admin)
        ->put(route('admin.rooms.contents.update', [$room, $content]), ['quantity' => 0])
        ->assertRedirect();

    expect($room->contents()->count())->toBe(0);
});
