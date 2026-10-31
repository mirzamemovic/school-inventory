<?php

use App\Enums\ActorType;
use App\Models\AssetEvent;
use App\Models\BookTitle;
use App\Models\Category;
use App\Models\Person;
use App\Models\Report;
use App\Models\Room;
use App\Models\RoomContent;
use App\Models\Setting;
use App\Models\Supply;
use App\Models\User;
use App\Support\EventAction;
use App\Support\EventSentence;

/*
 * Spec §5: every write path writes to the audit log, no exceptions. These are
 * the master-data paths that used to change things without a trace.
 */

beforeEach(function () {
    $this->admin = User::factory()->create(['name' => 'Drita Berisha']);
    $this->actingAs($this->admin);
});

function onlyEvent(string $action): AssetEvent
{
    return AssetEvent::query()->where('action', $action)->sole();
}

// --- Rooms and furniture ------------------------------------------------------

it('logs a room being added', function () {
    $this->post(route('admin.rooms.store'), ['name' => 'Lab 3', 'type' => 'lab'])->assertRedirect();

    $event = onlyEvent(EventAction::ROOM_CREATED);
    expect($event->subject_type)->toBe('room')
        ->and($event->actor_type)->toBe(ActorType::Admin)
        ->and(EventSentence::for($event))->toBe('Drita Berisha added the room Lab 3');
});

it('logs a room being renamed, with before and after', function () {
    $room = Room::factory()->create(['name' => 'Lab 2']);

    $this->put(route('admin.rooms.update', $room), ['name' => 'Room 14', 'floor' => $room->floor, 'type' => $room->type->value]);

    $event = onlyEvent(EventAction::ROOM_EDITED);
    expect($event->meta['name'])->toBeJson(['from' => 'Lab 2', 'to' => 'Room 14'])
        ->and(EventSentence::for($event))->toBe('Drita Berisha changed the room Room 14 from Lab 2 to Room 14');
});

it('logs nothing for a room saved without changes', function () {
    $room = Room::factory()->create();

    $this->put(route('admin.rooms.update', $room), ['name' => $room->name, 'floor' => $room->floor, 'type' => $room->type->value]);

    expect(AssetEvent::query()->where('action', EventAction::ROOM_EDITED)->count())->toBe(0);
});

it('logs a room being marked as counted', function () {
    $room = Room::factory()->create(['name' => 'Room 12']);

    $this->post(route('admin.rooms.counted', $room));

    expect(EventSentence::for(onlyEvent(EventAction::ROOM_COUNTED)))->toBe('Drita Berisha marked Room 12 as counted');
});

it('logs furniture being added to a room', function () {
    $room = Room::factory()->create(['name' => 'Room 12']);

    $this->post(route('admin.rooms.contents.store', $room), ['item_name' => 'Chairs', 'quantity' => 30, 'condition' => 'good']);

    $event = onlyEvent(EventAction::ROOM_FURNITURE_ADDED);
    expect($event->subject_id)->toBe($room->id)
        ->and($event->meta['quantity'])->toBe(30)
        ->and(EventSentence::for($event))->toBe('Drita Berisha added Chairs to Room 12');
});

it('logs a furniture count changing, and being set to zero, which removes the line', function () {
    $room = Room::factory()->create(['name' => 'Room 12']);
    $chairs = RoomContent::factory()->create(['room_id' => $room->id, 'item_name' => 'Chairs', 'quantity' => 30]);

    $this->put(route('admin.rooms.contents.update', [$room, $chairs]), ['quantity' => 28]);
    $this->put(route('admin.rooms.contents.update', [$room, $chairs]), ['quantity' => 0]);

    expect(RoomContent::query()->whereKey($chairs->id)->exists())->toBeFalse();

    $events = AssetEvent::query()->where('action', EventAction::ROOM_FURNITURE_CHANGED)->orderBy('id')->get();
    expect($events)->toHaveCount(2)
        ->and(EventSentence::for($events[0]))->toBe('Drita Berisha changed the number of Chairs in Room 12 from 30 to 28')
        ->and($events[1]->meta['quantity'])->toBeJson(['from' => 28, 'to' => 0]);
});

it('does not let one room\'s address change another room\'s furniture', function () {
    $room = Room::factory()->create();
    $other = Room::factory()->create();
    $chairs = RoomContent::factory()->create(['room_id' => $other->id, 'quantity' => 30]);

    $this->put(route('admin.rooms.contents.update', [$room, $chairs]), ['quantity' => 0])->assertNotFound();

    expect($chairs->fresh()->quantity)->toBe(30)
        ->and(AssetEvent::query()->count())->toBe(0);
});

// --- Categories ---------------------------------------------------------------

it('logs categories being added and changed', function () {
    $this->post(route('admin.categories.store'), ['name' => 'Tablets', 'prefix' => 'TAB', 'needs_inspection_default' => 0]);

    $category = Category::query()->where('name', 'Tablets')->sole();
    $this->put(route('admin.categories.update', $category), ['name' => 'Tablets', 'prefix' => 'TAB', 'needs_inspection_default' => 1, 'default_inspection_interval_months' => 12]);

    expect(EventSentence::for(onlyEvent(EventAction::CATEGORY_CREATED)))->toBe('Drita Berisha added the category Tablets')
        ->and(onlyEvent(EventAction::CATEGORY_EDITED)->meta['needs_inspection_default'])->toBeJson(['from' => false, 'to' => true]);
});

// --- Settings -----------------------------------------------------------------

it('logs a change of settings against the admin who made it', function () {
    Setting::put('school_name', 'Gjimnazi Test');

    $this->put(route('admin.settings.update'), ['school_name' => 'Gjimnazi Test']);

    $event = onlyEvent(EventAction::SETTINGS_CHANGED);
    expect($event->subject_type)->toBe('user')
        ->and($event->subject_id)->toBe($this->admin->id)
        ->and($event->meta)->toBeJson(['student_reporting_enabled' => ['from' => '1', 'to' => '0']]);
});

it('logs nothing when the settings are saved unchanged', function () {
    Setting::put('school_name', 'Gjimnazi Test');

    $this->put(route('admin.settings.update'), ['school_name' => 'Gjimnazi Test', 'student_reporting_enabled' => 1]);

    expect(AssetEvent::query()->where('action', EventAction::SETTINGS_CHANGED)->count())->toBe(0);
});

// --- People -------------------------------------------------------------------

it('logs a person being edited, and reactivated', function () {
    $person = Person::factory()->create(['name' => 'Ana B', 'type' => 'student', 'class' => '10-1', 'active' => false]);

    $this->put(route('admin.people.update', $person), ['name' => 'Ana B', 'type' => 'student', 'class' => '11-1']);
    $this->post(route('admin.people.reactivate', $person));

    expect(onlyEvent(EventAction::PERSON_EDITED)->meta['class'])->toBeJson(['from' => '10-1', 'to' => '11-1'])
        ->and(EventSentence::for(onlyEvent(EventAction::PERSON_EDITED)))->toBe("Drita Berisha changed a person's details")
        ->and(EventSentence::for(onlyEvent(EventAction::PERSON_REACTIVATED)))->toBe('Drita Berisha reactivated a person');
});

it('erases a renamed person\'s earlier name and email as well as the current ones', function () {
    $person = Person::factory()->create(['name' => 'Ana B', 'type' => 'student', 'class' => '10-1', 'email' => 'ana.b@school.example']);
    $report = Report::factory()->create(['reporter_name' => 'Ana B']);

    $this->put(route('admin.people.update', $person), [
        'name' => 'Ana Berisha', 'type' => 'student', 'class' => '10-1', 'email' => 'ana.berisha@school.example',
    ]);

    $this->artisan('person:erase', ['id' => $person->id])
        ->expectsConfirmation(__('retention.confirm_erase'), 'yes')
        ->assertSuccessful();

    $log = json_encode(AssetEvent::query()->get()->map->getAttributes());

    expect($log)->not->toContain('Ana B')
        ->and($log)->not->toContain('ana.b@school.example')
        ->and($log)->not->toContain('ana.berisha@school.example')
        ->and($report->fresh()->reporter_name)->toBe(__('retention.removed_person', ['id' => $person->id]));
});

// --- Supplies -----------------------------------------------------------------

it('logs supplies being added, and stock set directly on the edit form', function () {
    $this->post(route('admin.supplies.store'), ['name' => 'A4 paper', 'unit' => 'reams', 'quantity' => 40, 'minimum_level' => 10]);

    $supply = Supply::query()->where('name', 'A4 paper')->sole();
    $this->put(route('admin.supplies.update', $supply), ['name' => 'A4 paper', 'unit' => 'reams', 'quantity' => 9999, 'minimum_level' => 10]);

    expect(EventSentence::for(onlyEvent(EventAction::SUPPLY_ADDED)))->toBe('Drita Berisha added the supply A4 paper')
        ->and(EventSentence::for(onlyEvent(EventAction::SUPPLY_EDITED)))->toBe('Drita Berisha changed the supply A4 paper from 40 to 9999');
});

// --- Book titles --------------------------------------------------------------

it('logs book titles being added and changed', function () {
    $this->post(route('admin.books.titles.store'), ['title' => 'Matematika 10', 'grade' => 10, 'total_copies' => 30]);

    $title = BookTitle::query()->where('title', 'Matematika 10')->sole();
    $this->put(route('admin.books.titles.update', $title), ['title' => 'Matematika 10', 'grade' => 10, 'total_copies' => 28]);

    expect(onlyEvent(EventAction::BOOK_TITLE_ADDED)->subject_type)->toBe('book_title')
        ->and(EventSentence::for(onlyEvent(EventAction::BOOK_TITLE_EDITED)))->toBe('Drita Berisha changed the book Matematika 10 from 30 to 28');
});

// --- Every action reads as a sentence -----------------------------------------

it('has a sentence for every action, so none falls back to "did something"', function () {
    foreach (EventAction::all() as $action) {
        expect(__('activity.sentence.'.$action))->not->toBe('activity.sentence.'.$action, "No sentence for {$action}");
    }
});
