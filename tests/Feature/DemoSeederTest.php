<?php

use App\Enums\PersonType;
use App\Models\Asset;
use App\Models\BookLoan;
use App\Models\BookTitle;
use App\Models\License;
use App\Models\Person;
use App\Models\Report;
use App\Models\Room;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RoomSeeder;
use Database\Seeders\SettingSeeder;

function seedDemo(): void
{
    test()->seed([CategorySeeder::class, RoomSeeder::class, SettingSeeder::class, DemoSeeder::class]);
}

/* -- the guard ---------------------------------------------------------- */

it('refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new DemoSeeder)->run())
        ->toThrow(RuntimeException::class, 'must never run in production');

    expect(Person::query()->count())->toBe(0);
});

it('is never pulled in by the ordinary seeder', function () {
    // db:seed and migrate --seed must not produce fictional students.
    expect(file_get_contents(database_path('seeders/DatabaseSeeder.php')))
        ->not->toContain('DemoSeeder');
});

/* -- every invented person says so -------------------------------------- */

it('marks every person as fictional', function () {
    seedDemo();

    expect(Person::query()->where('name', 'not like', '%(demo)')->count())->toBe(0)
        ->and(Person::query()->count())->toBeGreaterThan(100);
});

it('gives everybody a distinct name', function () {
    seedDemo();

    $names = Person::query()->pluck('name');

    expect($names->unique()->count())->toBe($names->count());
});

it('keeps the invented email addresses on a reserved example domain', function () {
    seedDemo();

    expect(Person::query()->whereNotNull('email')->where('email', 'not like', '%@demo-school.example')->count())
        ->toBe(0);
});

/* -- the shape asked for ------------------------------------------------- */

it('builds a school of the size described', function () {
    seedDemo();

    expect(Room::query()->count())->toBe(12)
        ->and(Asset::query()->count())->toBeGreaterThanOrEqual(55)
        ->and(Asset::query()->count())->toBeLessThanOrEqual(70)
        ->and(Person::query()->where('type', PersonType::Staff->value)->count())->toBe(20)
        ->and(Person::query()->where('type', PersonType::Student->value)->count())->toBe(100)
        ->and(Person::query()->students()->distinct()->count('class'))->toBe(10)
        ->and(BookTitle::query()->count())->toBe(30);
});

it('spreads the assets across categories rather than piling them into one', function () {
    seedDemo();

    expect(Asset::query()->distinct()->count('category_id'))->toBeGreaterThanOrEqual(10);
});

it('puts every asset in a room', function () {
    seedDemo();

    expect(Asset::query()->whereNull('room_id')->count())->toBe(0);
});

it('issues books that are mostly still out', function () {
    seedDemo();

    expect(BookLoan::query()->count())->toBeGreaterThan(200)
        ->and(BookLoan::query()->whereNull('returned_at')->count())->toBeGreaterThan(0)
        ->and(BookLoan::query()->whereNotNull('returned_at')->count())->toBeGreaterThan(0);
});

it('leaves some reports open and some closed', function () {
    seedDemo();

    expect(Report::query()->open()->count())->toBeGreaterThan(0)
        ->and(Report::query()->closed()->count())->toBeGreaterThan(0)
        ->and(Report::query()->inProgress()->count())->toBeGreaterThan(0);
});

it('includes a perpetual licence with no expiry date', function () {
    seedDemo();

    expect(License::query()->count())->toBeGreaterThanOrEqual(4)
        ->and(License::query()->whereNull('expires_at')->count())->toBe(1);

    $perpetual = License::query()->whereNull('expires_at')->firstOrFail();

    expect($perpetual->isExpired())->toBeFalse()
        ->and($perpetual->neverExpires())->toBeTrue();
});

it('leaves the demo school with something on every screen worth showing', function () {
    seedDemo();

    // Licences expiring, a licence already expired, and stock below minimum:
    // the home screen has real rows rather than an empty state.
    expect(License::query()->expiringWithin(60)->count())->toBeGreaterThan(0)
        ->and(\App\Models\Supply::query()->low()->count())->toBeGreaterThan(0)
        ->and(\App\Services\InspectionRecorder::overdue()->count())->toBeGreaterThan(0)
        ->and(\App\Models\AssetEvent::query()->count())->toBeGreaterThan(50);
});

it('can be run twice without duplicating the school', function () {
    seedDemo();

    $before = [
        'people' => Person::query()->count(),
        'rooms' => Room::query()->count(),
        'assets' => Asset::query()->count(),
        'titles' => BookTitle::query()->count(),
        'loans' => BookLoan::query()->count(),
        'reports' => Report::query()->count(),
        'licenses' => License::query()->count(),
        'inspections' => \App\Models\Inspection::query()->count(),
    ];

    test()->seed(DemoSeeder::class);

    expect([
        'people' => Person::query()->count(),
        'rooms' => Room::query()->count(),
        'assets' => Asset::query()->count(),
        'titles' => BookTitle::query()->count(),
        'loans' => BookLoan::query()->count(),
        'reports' => Report::query()->count(),
        'licenses' => License::query()->count(),
        'inspections' => \App\Models\Inspection::query()->count(),
    ])->toBe($before);
});

it('gives every demo asset a serial of its own', function () {
    seedDemo();

    $serials = Asset::query()->whereNotNull('serial_number')->pluck('serial_number');

    expect($serials->unique()->count())->toBe($serials->count());
});
