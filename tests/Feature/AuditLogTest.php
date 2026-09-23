<?php

use App\Enums\ActorType;
use App\Exceptions\AuditLogIsAppendOnly;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\EventAction;

it('records who did what to which thing', function () {
    $asset = Asset::factory()->create();
    $user = User::factory()->create(['name' => 'Drita Berisha']);
    $this->actingAs($user);

    $event = app(AuditLogger::class)->record($asset, EventAction::ASSET_MOVED, [
        'room' => ['from' => 'Lab 2', 'to' => 'Room 14'],
    ]);

    expect($event->subject_type)->toBe('asset')
        ->and($event->subject_id)->toBe($asset->id)
        ->and($event->action)->toBe(EventAction::ASSET_MOVED)
        ->and($event->actor_type)->toBe(ActorType::Admin)
        ->and($event->actor_name)->toBe('Drita Berisha')
        ->and($event->meta['room']['to'])->toBe('Room 14');
});

it('falls back to the system actor when nobody is signed in', function () {
    $event = app(AuditLogger::class)->record(Asset::factory()->create(), EventAction::ASSET_CREATED);

    expect($event->actor_type)->toBe(ActorType::System);
});

it('never lets a logged row be updated', function () {
    $event = app(AuditLogger::class)->record(Asset::factory()->create(), EventAction::ASSET_CREATED);

    expect(fn () => $event->update(['action' => EventAction::ASSET_RETIRED]))
        ->toThrow(AuditLogIsAppendOnly::class);
});

it('never lets a logged row be deleted', function () {
    $event = app(AuditLogger::class)->record(Asset::factory()->create(), EventAction::ASSET_CREATED);

    expect(fn () => $event->delete())->toThrow(AuditLogIsAppendOnly::class);
});

it('has no updated_at column to keep, because the log is append only', function () {
    expect(AssetEvent::UPDATED_AT)->toBeNull()
        ->and(Schema::hasColumn('asset_events', 'updated_at'))->toBeFalse();
});

it('marks admin actions as verified and everything else as self-reported', function () {
    $asset = Asset::factory()->create();
    $logger = app(AuditLogger::class);

    $this->actingAs(User::factory()->create());
    $admin = $logger->record($asset, EventAction::ASSET_EDITED);

    expect($admin->isSelfReported())->toBeFalse();
});

it('turns a before and after pair into useful meta', function () {
    $changes = app(AuditLogger::class)->changes(
        ['room' => 'Lab 2', 'status' => 'in_use'],
        ['room' => 'Room 14', 'status' => 'in_use'],
    );

    expect($changes)->toBe(['room' => ['from' => 'Lab 2', 'to' => 'Room 14']]);
});
