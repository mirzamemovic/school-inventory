<?php

use App\Enums\ActorType;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\BlockedDevice;
use App\Models\BookLoan;
use App\Models\Inspection;
use App\Models\Person;
use App\Models\Report;
use App\Services\AuditLogger;
use App\Services\PersonEraser;
use App\Support\Actor;
use App\Support\EventAction;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->person = Person::factory()->create(['name' => 'Ana Berisha', 'email' => 'ana@school.example']);
});

function eraseAna(): void
{
    test()->artisan('person:erase', ['id' => test()->person->id])
        ->expectsConfirmation(__('retention.confirm_erase'), 'yes')
        ->assertSuccessful();
}

/* -- the log keeps every row ------------------------------------------- */

it('leaves the audit log row count exactly as it was', function () {
    $asset = Asset::factory()->create();
    $logger = app(AuditLogger::class);
    $actor = new Actor(ActorType::Staff, null, 'Ana Berisha', 'device-abc');

    $logger->record($this->person, EventAction::PERSON_ADDED, ['name' => 'Ana Berisha'], $actor);
    $logger->record($asset, EventAction::ASSET_MOVED, ['room' => ['from' => 'Lab 2', 'to' => 'Room 14']], $actor);
    $logger->record($asset, EventAction::ASSET_EDITED, [], $actor);

    $before = AssetEvent::query()->count();

    eraseAna();

    // One row added by the erasure itself, none removed.
    expect(AssetEvent::query()->count())->toBe($before + 1)
        ->and(AssetEvent::query()->where('action', EventAction::PERSON_ERASED)->count())->toBe(1);
});

it('refuses to let anything delete an audit row, even now', function () {
    $event = app(AuditLogger::class)->record($this->person, EventAction::PERSON_ADDED);

    expect(fn () => $event->delete())->toThrow(\App\Exceptions\AuditLogIsAppendOnly::class);
});

/* -- the meta JSON, which is the easy one to miss ---------------------- */

it('removes the name from the stored details, not just from the actor column', function () {
    $logger = app(AuditLogger::class);
    $actor = new Actor(ActorType::Admin, null, 'Somebody Else');

    $logger->record($this->person, EventAction::PERSON_ADDED, ['name' => 'Ana Berisha'], $actor);

    eraseAna();

    $event = AssetEvent::query()->where('action', EventAction::PERSON_ADDED)->firstOrFail();

    expect($event->meta['name'])->toBe('Removed person #'.$this->person->id)
        ->and(json_encode($event->meta))->not->toContain('Ana Berisha');
});

it('reaches a name nested inside the details', function () {
    $asset = Asset::factory()->create();

    app(AuditLogger::class)->record($asset, EventAction::ASSET_ASSIGNED, [
        'person' => ['from' => 'Ana Berisha', 'to' => 'Behar Krasniqi'],
    ]);

    eraseAna();

    $event = AssetEvent::query()->where('action', EventAction::ASSET_ASSIGNED)->firstOrFail();

    expect($event->meta['person']['from'])->toBe('Removed person #'.$this->person->id)
        ->and($event->meta['person']['to'])->toBe('Behar Krasniqi');
});

it('leaves the name of an asset or a licence alone', function () {
    // Keying on "name" rather than on the value would wipe equipment history
    // to satisfy a request about a person.
    $asset = Asset::factory()->create();
    $logger = app(AuditLogger::class);

    $logger->record($asset, EventAction::ASSET_CREATED, ['name' => 'Epson projector', 'room' => 'Room 12']);
    $logger->record($asset, EventAction::LICENCE_CREATED, ['name' => 'Microsoft 365', 'vendor' => 'Microsoft']);

    eraseAna();

    expect(AssetEvent::query()->where('action', EventAction::ASSET_CREATED)->firstOrFail()->meta['name'])
        ->toBe('Epson projector')
        ->and(AssetEvent::query()->where('action', EventAction::LICENCE_CREATED)->firstOrFail()->meta['name'])
        ->toBe('Microsoft 365');
});

it('matches the name whatever the case or the stray spaces', function () {
    app(AuditLogger::class)->record($this->person, EventAction::PERSON_ADDED, ['name' => '  ana berisha ']);

    eraseAna();

    expect(json_encode(AssetEvent::query()->where('action', EventAction::PERSON_ADDED)->firstOrFail()->meta))
        ->not->toContain('berisha');
});

/* -- identifiers -------------------------------------------------------- */

it('clears the device token and the IP address on both tables', function () {
    $asset = Asset::factory()->create();

    Report::factory()->create([
        'reporter_name' => 'Ana Berisha',
        'device_token' => 'device-abc',
        'ip_address' => '10.0.0.7',
    ]);

    app(AuditLogger::class)->record($asset, EventAction::REPORT_CREATED, [],
        new Actor(ActorType::Staff, null, 'Ana Berisha', 'device-abc'));

    DB::table('asset_events')->update(['ip_address' => '10.0.0.7']);

    eraseAna();

    expect(DB::table('reports')->whereNotNull('device_token')->count())->toBe(0)
        ->and(DB::table('reports')->whereNotNull('ip_address')->count())->toBe(0)
        ->and(DB::table('asset_events')->where('device_token', 'device-abc')->count())->toBe(0)
        ->and(DB::table('asset_events')->where('ip_address', '10.0.0.7')->count())->toBe(0);
});

it('leaves a block list entry standing', function () {
    // Blocking a device is a control on a device, with its own justification.
    BlockedDevice::query()->create(['device_token' => 'device-abc', 'note' => 'Joke reports']);

    app(AuditLogger::class)->record($this->person, EventAction::REPORT_CREATED, [],
        new Actor(ActorType::Student, null, 'Ana Berisha', 'device-abc'));

    eraseAna();

    expect(BlockedDevice::blocks('device-abc'))->toBeTrue();
});

/* -- the rest of the record --------------------------------------------- */

it('pseudonymises the person and nulls their contact details', function () {
    eraseAna();

    $person = $this->person->fresh();

    expect($person->name)->toBe('Removed person #'.$person->id)
        ->and($person->email)->toBeNull()
        ->and($person->active)->toBeFalse();
});

it('keeps their book loans, pointing at the pseudonym', function () {
    $loan = BookLoan::factory()->create(['person_id' => $this->person->id]);

    eraseAna();

    expect(BookLoan::query()->whereKey($loan->id)->exists())->toBeTrue()
        ->and($loan->fresh()->person->name)->toBe('Removed person #'.$this->person->id);
});

it('renames them on their reports', function () {
    Report::factory()->create(['reporter_name' => 'Ana Berisha']);
    Report::factory()->create(['reporter_name' => 'Somebody Else']);

    eraseAna();

    expect(Report::query()->where('reporter_name', 'Ana Berisha')->count())->toBe(0)
        ->and(Report::query()->where('reporter_name', 'Somebody Else')->count())->toBe(1);
});

it('scrubs inspections recorded under that exact name', function () {
    $mine = Inspection::factory()->create(['inspector_name' => 'Ana Berisha']);
    $theirs = Inspection::factory()->create(['inspector_name' => 'Behar Krasniqi']);

    eraseAna();

    expect($mine->fresh()->inspector_name)->toBe('Removed person #'.$this->person->id)
        ->and($theirs->fresh()->inspector_name)->toBe('Behar Krasniqi');
});

it('leaves the date of a safety check alone when it scrubs the inspector', function () {
    // Older MariaDB and MySQL rewrite the first timestamp column in a table on
    // every update. A check done in 2025 must not move to the day of erasure.
    $inspection = Inspection::factory()->create([
        'inspector_name' => 'Ana Berisha',
        'inspected_at' => '2025-03-04 09:30:00',
    ]);

    eraseAna();

    expect($inspection->fresh()->inspector_name)->not->toBe('Ana Berisha')
        ->and($inspection->fresh()->inspected_at->toDateTimeString())->toBe('2025-03-04 09:30:00');
});

it('shows the matched inspections before doing anything', function () {
    $asset = Asset::factory()->create(['name' => 'Fire extinguisher, corridor']);
    Inspection::factory()->create(['inspector_name' => 'Ana Berisha', 'asset_id' => $asset->id]);

    $this->artisan('person:erase', ['id' => $this->person->id])
        ->expectsOutputToContain(__('retention.inspections_caveat'))
        ->expectsOutputToContain('Fire extinguisher, corridor')
        ->expectsConfirmation(__('retention.confirm_erase'), 'no')
        ->assertSuccessful();

    // Said no, so nothing moved.
    expect($this->person->fresh()->name)->toBe('Ana Berisha');
});

/* -- the closing event -------------------------------------------------- */

it('logs a person.erased event that names nobody', function () {
    eraseAna();

    $event = AssetEvent::query()->where('action', EventAction::PERSON_ERASED)->firstOrFail();

    expect($event->subject_type)->toBe('person')
        ->and($event->subject_id)->toBe($this->person->id)
        ->and(json_encode($event->meta))->not->toContain('Ana Berisha')
        ->and(json_encode($event->meta))->not->toContain('ana@school.example')
        ->and($event->actor_name)->not->toBe('Ana Berisha');
});

it('says plainly when there is no such person', function () {
    $this->artisan('person:erase', ['id' => 9999])->assertFailed();
});

/* -- the privacy note ---------------------------------------------------- */

it('offers a privacy note from the report form, with no login', function () {
    $asset = Asset::factory()->create();

    $this->get(route('report.create', $asset->asset_tag))
        ->assertOk()
        ->assertSee(__('privacy.link'))
        ->assertSee(route('privacy'), escape: false);

    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee(__('privacy.what'))
        ->assertSee(__('privacy.why'))
        ->assertSee(__('privacy.how_long'))
        ->assertSee('noindex, nofollow', escape: false);
});
