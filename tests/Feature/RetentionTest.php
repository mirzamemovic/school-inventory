<?php

use App\Models\AssetEvent;
use App\Models\BookLoan;
use App\Models\BookTitle;
use App\Models\Person;
use App\Services\RetentionPolicy;
use App\Support\AcademicYear;
use App\Support\EventAction;
use Illuminate\Console\Scheduling\Schedule;

/*
| The academic year ends on 15 June. "Now" in these tests is whatever the
| suite's clock says, so loans are pinned to explicit years rather than to
| offsets from today.
*/

function loanIssuedIn(string $academicYear, array $overrides = []): BookLoan
{
    return BookLoan::factory()->create(array_merge([
        'academic_year' => $academicYear,
        'issued_at' => explode('/', $academicYear)[0].'-09-15',
        'due_at' => AcademicYear::endsOn($academicYear)->toDateString(),
        'book_title_id' => BookTitle::factory(),
    ], $overrides));
}

/* -- rule one: returned loans ------------------------------------------ */

it('never deletes an open loan, however old it is', function () {
    $open = loanIssuedIn('2019/2020', ['returned_at' => null]);

    expect(app(RetentionPolicy::class)->expiredLoans())->toBeEmpty()
        ->and(app(RetentionPolicy::class)->loanHasExpired($open))->toBeFalse();

    $this->artisan('data:enforce-retention', ['--dry-run' => true])->assertSuccessful();

    expect(BookLoan::query()->whereKey($open->id)->exists())->toBeTrue();
});

it('deletes a loan returned two academic years ago', function () {
    $old = loanIssuedIn('2019/2020', ['returned_at' => '2020-06-01']);

    expect(app(RetentionPolicy::class)->expiredLoans()->pluck('id')->all())->toBe([$old->id]);

    $this->artisan('data:enforce-retention')
        ->expectsConfirmation(__('retention.confirm'), 'yes')
        ->assertSuccessful();

    expect(BookLoan::query()->whereKey($old->id)->exists())->toBeFalse();
});

it('keeps a returned loan until the year after the one it was issued in has ended', function () {
    // Issued 2024/2025, returned. Kept through all of 2025/2026, gone after
    // 15 June 2026.
    $loan = loanIssuedIn('2024/2025', ['returned_at' => '2025-06-01']);
    $policy = app(RetentionPolicy::class);

    $this->travelTo('2026-06-14');
    expect($policy->loanHasExpired($loan->fresh()))->toBeFalse();

    $this->travelTo('2026-06-16');
    expect($policy->loanHasExpired($loan->fresh()))->toBeTrue();
});

it('works out the year after the one a loan belongs to', function () {
    expect(app(RetentionPolicy::class)->yearAfter('2024/2025'))->toBe('2025/2026')
        ->and(app(RetentionPolicy::class)->yearAfter('2019/2020'))->toBe('2020/2021');
});

/* -- rule two: people, read from the column ---------------------------- */

it('reads the leaving date from the column, not from the audit log', function () {
    // Deactivated 13 months ago goes; deactivated 6 months ago stays.
    $gone = Person::factory()->create(['name' => 'Long gone']);
    $recent = Person::factory()->create(['name' => 'Recently left']);

    $this->travelTo(now()->subMonths(13));
    $gone->update(['active' => false]);

    $this->travelTo(now()->addMonths(7));   // 6 months ago
    $recent->update(['active' => false]);

    $this->travelBack();

    expect($gone->fresh()->left_at)->not->toBeNull()
        ->and($recent->fresh()->left_at)->not->toBeNull();

    // Nothing in the log is consulted — delete every event first and the rule
    // still fires correctly.
    DB::table('asset_events')->delete();

    $toDelete = app(RetentionPolicy::class)->peopleToDelete();

    expect($toDelete->pluck('id')->all())->toBe([$gone->id]);
});

it('deletes a person and their loans a year after they left', function () {
    $person = Person::factory()->create();
    loanIssuedIn('2024/2025', ['person_id' => $person->id, 'returned_at' => '2025-06-01']);

    $this->travelTo(now()->subMonths(13));
    $person->update(['active' => false]);
    $this->travelBack();

    $this->artisan('data:enforce-retention')
        ->expectsConfirmation(__('retention.confirm'), 'yes')
        ->assertSuccessful();

    expect(Person::query()->whereKey($person->id)->exists())->toBeFalse()
        ->and(BookLoan::query()->where('person_id', $person->id)->exists())->toBeFalse();
});

it('holds back somebody long gone who still has a book out', function () {
    $person = Person::factory()->create(['name' => 'Owes a book']);
    loanIssuedIn('2019/2020', ['person_id' => $person->id, 'returned_at' => null]);

    $this->travelTo(now()->subMonths(20));
    $person->update(['active' => false]);
    $this->travelBack();

    $policy = app(RetentionPolicy::class);

    expect($policy->peopleToDelete())->toBeEmpty()
        ->and($policy->peopleHeldBack()->pluck('id')->all())->toBe([$person->id]);

    $this->artisan('data:enforce-retention')
        ->expectsOutputToContain('Owes a book')
        ->assertSuccessful();

    expect(Person::query()->whereKey($person->id)->exists())->toBeTrue();
});

/* -- the observer keeps the column honest ------------------------------ */

it('stamps a leaving date when somebody is deactivated and clears it when they return', function () {
    $person = Person::factory()->create();

    expect($person->left_at)->toBeNull();

    $person->update(['active' => false]);
    expect($person->fresh()->left_at?->toDateString())->toBe(now()->toDateString());

    $person->update(['active' => true]);
    expect($person->fresh()->left_at)->toBeNull();
});

/* -- the command itself ------------------------------------------------- */

it('changes nothing on a dry run', function () {
    $loan = loanIssuedIn('2019/2020', ['returned_at' => '2020-06-01']);

    $this->artisan('data:enforce-retention', ['--dry-run' => true])
        ->expectsOutputToContain(__('retention.dry_run_notice'))
        ->assertSuccessful();

    expect(BookLoan::query()->whereKey($loan->id)->exists())->toBeTrue();
});

it('deletes nothing when the operator says no', function () {
    $loan = loanIssuedIn('2019/2020', ['returned_at' => '2020-06-01']);

    $this->artisan('data:enforce-retention')
        ->expectsConfirmation(__('retention.confirm'), 'no')
        ->expectsOutputToContain(__('retention.cancelled'))
        ->assertSuccessful();

    expect(BookLoan::query()->whereKey($loan->id)->exists())->toBeTrue();
});

it('says so plainly when there is nothing to remove', function () {
    $this->artisan('data:enforce-retention')
        ->expectsOutputToContain(__('retention.nothing_to_do'))
        ->assertSuccessful();
});

it('records that retention ran, without naming anybody', function () {
    $person = Person::factory()->create(['name' => 'Ana Berisha']);
    loanIssuedIn('2019/2020', ['person_id' => $person->id, 'returned_at' => '2020-06-01']);

    $this->travelTo(now()->subMonths(13));
    $person->update(['active' => false]);
    $this->travelBack();

    $this->artisan('data:enforce-retention')
        ->expectsConfirmation(__('retention.confirm'), 'yes')
        ->assertSuccessful();

    $event = AssetEvent::query()->where('action', EventAction::DATA_RETENTION_ENFORCED)->firstOrFail();

    expect($event->actor_name)->not->toBe('Ana Berisha')
        ->and(json_encode($event->meta))->not->toContain('Ana Berisha');
});

it('is never scheduled', function () {
    $scheduled = collect(app(Schedule::class)->events())
        ->map(fn ($event) => $event->command ?? '');

    expect($scheduled->filter(fn ($c) => str_contains($c, 'data:enforce-retention')))->toBeEmpty()
        ->and($scheduled->filter(fn ($c) => str_contains($c, 'person:erase')))->toBeEmpty();
});
