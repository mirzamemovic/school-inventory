<?php

use App\Enums\LoanCondition;
use App\Models\AssetEvent;
use App\Models\BookLoan;
use App\Models\BookTitle;
use App\Models\Person;
use App\Models\User;
use App\Support\AcademicYear;
use App\Support\EventAction;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

it('keeps the books screens behind a password', function () {
    $this->get(route('admin.books.titles'))->assertRedirect(route('login'));
});

it('shows how many copies are owned, out and available', function () {
    $title = BookTitle::factory()->create(['total_copies' => 40]);
    BookLoan::factory()->count(3)->create(['book_title_id' => $title->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.books.titles'))
        ->assertOk()
        ->assertSee($title->title)
        ->assertSee('40');

    expect($title->copiesOut())->toBe(3)
        ->and($title->copiesAvailable())->toBe(37);
});

it('treats the grade 10 set as simply every title for grade 10', function () {
    BookTitle::factory()->count(3)->forGrade(10)->create();
    BookTitle::factory()->count(2)->forGrade(11)->create();

    $students = Person::factory()->count(2)->inClass('10a')->create();

    $response = $this->actingAs($this->admin)
        ->get(route('admin.books.issue.form', ['class' => '10a']))
        ->assertOk();

    // The list is ordered by name, so compare the set rather than the order
    // the factory happened to create them in.
    expect($response->viewData('titles'))->toHaveCount(3)
        ->and($response->viewData('students')->pluck('id')->sort()->values()->all())
        ->toBe($students->pluck('id')->sort()->values()->all());
});

it('issues a whole class in one confirmation', function () {
    $titles = BookTitle::factory()->count(3)->forGrade(10)->create();
    $students = Person::factory()->count(4)->inClass('10a')->create();

    $selection = $students->mapWithKeys(fn ($s) => [$s->id => $titles->pluck('id')->all()])->all();

    $this->actingAs($this->admin)
        ->post(route('admin.books.issue'), ['class' => '10a', 'selection' => $selection])
        ->assertRedirect();

    expect(BookLoan::query()->count())->toBe(12)
        ->and(BookLoan::query()->first()->due_at->toDateString())
        ->toBe(AcademicYear::endsOn()->toDateString());
});

it('does not hand somebody a second copy of a book they already hold', function () {
    $title = BookTitle::factory()->forGrade(10)->create();
    $student = Person::factory()->inClass('10a')->create();

    $payload = ['class' => '10a', 'selection' => [$student->id => [$title->id]]];

    $this->actingAs($this->admin)->post(route('admin.books.issue'), $payload);
    $this->actingAs($this->admin)->post(route('admin.books.issue'), $payload);

    expect(BookLoan::query()->count())->toBe(1);
});

it('writes an issue to the audit log', function () {
    $title = BookTitle::factory()->forGrade(10)->create();
    $student = Person::factory()->inClass('10a')->create();

    $this->actingAs($this->admin)->post(route('admin.books.issue'), [
        'class' => '10a',
        'selection' => [$student->id => [$title->id]],
    ]);

    $event = AssetEvent::query()->where('action', EventAction::BOOK_ISSUED)->firstOrFail();

    expect($event->subject_type)->toBe('person')
        ->and($event->subject_id)->toBe($student->id);
});

it('lists who still owes what in June', function () {
    $student = Person::factory()->inClass('10a')->create(['name' => 'Arben Krasniqi']);
    $loan = BookLoan::factory()->create(['person_id' => $student->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.books.returns.form', ['class' => '10a']))
        ->assertOk()
        ->assertSee('Arben Krasniqi')
        ->assertSee($loan->title->title);
});

it('takes books back and records the condition', function () {
    $student = Person::factory()->inClass('10a')->create();
    $loan = BookLoan::factory()->create(['person_id' => $student->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.books.returns'), [
            'class' => '10a',
            'returned' => [$loan->id => LoanCondition::Damaged->value],
        ])
        ->assertRedirect();

    $loan->refresh();

    expect($loan->returned_at)->not->toBeNull()
        ->and($loan->condition_on_return)->toBe(LoanCondition::Damaged)
        ->and(AssetEvent::query()->where('action', EventAction::BOOK_RETURNED)->exists())->toBeTrue();
});

it('never takes the same book back twice', function () {
    $loan = BookLoan::factory()->returned()->create();

    $this->actingAs($this->admin)->post(route('admin.books.returns'), [
        'class' => '10a',
        'returned' => [$loan->id => LoanCondition::Lost->value],
    ]);

    expect($loan->fresh()->condition_on_return)->toBe(LoanCondition::Good);
});

it('marks a loan overdue once the school year has ended', function () {
    expect(BookLoan::factory()->overdue()->create()->isOverdue())->toBeTrue()
        ->and(BookLoan::factory()->create()->isOverdue())->toBeFalse();
});

it('lets one student be adjusted mid-year without touching the class', function () {
    $titles = BookTitle::factory()->count(2)->forGrade(10)->create();
    $student = Person::factory()->inClass('10a')->create();

    $this->actingAs($this->admin)->put(route('admin.books.student.update', $student), [
        'titles' => [$titles[0]->id],
    ]);

    expect($student->outstandingLoans()->pluck('book_title_id')->all())->toBe([$titles[0]->id]);

    $this->actingAs($this->admin)->put(route('admin.books.student.update', $student), [
        'titles' => [$titles[1]->id],
    ]);

    expect($student->fresh()->outstandingLoans()->pluck('book_title_id')->all())->toBe([$titles[1]->id])
        ->and(BookLoan::query()->count())->toBe(2);
});

it('shows a student what they hold now and what they held before', function () {
    $student = Person::factory()->inClass('10a')->create();
    BookLoan::factory()->create(['person_id' => $student->id]);
    $old = BookLoan::factory()->returned()->create([
        'person_id' => $student->id,
        'academic_year' => '2024/2025',
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.books.student', $student))
        ->assertOk();

    expect($response->viewData('previous')->pluck('id')->all())->toBe([$old->id]);
});

it('works out the school year and its end date', function () {
    expect(AcademicYear::forDate(\Carbon\CarbonImmutable::create(2026, 10, 1)))->toBe('2026/2027')
        ->and(AcademicYear::forDate(\Carbon\CarbonImmutable::create(2027, 3, 1)))->toBe('2026/2027')
        ->and(AcademicYear::endsOn('2026/2027')->toDateString())->toBe('2027-06-15');
});

it('rolls into the next school year over the summer, so nothing is issued already overdue', function () {
    // Books are due back on 15 June. A book handed out on 20 August belongs to
    // the year that is about to start, not the one that has just finished.
    expect(AcademicYear::forDate(\Carbon\CarbonImmutable::create(2026, 6, 14)))->toBe('2025/2026')
        ->and(AcademicYear::forDate(\Carbon\CarbonImmutable::create(2026, 8, 20)))->toBe('2026/2027')
        ->and(AcademicYear::endsOn(AcademicYear::forDate(\Carbon\CarbonImmutable::create(2026, 8, 20)))->toDateString())
        ->toBe('2027-06-15');
});

it('honours a school year pinned in settings while it still means something', function () {
    \App\Models\Setting::put('academic_year', '2099/2100');

    expect(AcademicYear::current())->toBe('2099/2100');
});

it('ignores a pinned school year that has already ended', function () {
    // Otherwise the first September nobody updates it, every book is issued
    // with a due date in the past and every class link is born expired.
    \App\Models\Setting::put('academic_year', '2019/2020');

    expect(AcademicYear::current())->toBe(AcademicYear::forDate(\Carbon\CarbonImmutable::now()))
        ->and(AcademicYear::endsOn()->isFuture())->toBeTrue();
});

it('never issues a book with a due date in the past', function () {
    \App\Models\Setting::put('academic_year', '2019/2020');

    $title = BookTitle::factory()->forGrade(10)->create();
    $student = Person::factory()->inClass('10a')->create();

    $this->actingAs($this->admin)->post(route('admin.books.issue'), [
        'class' => '10a',
        'selection' => [$student->id => [$title->id]],
    ]);

    expect(BookLoan::query()->firstOrFail()->due_at->isFuture())->toBeTrue();
});

it('opens the returns screen before any class has been picked', function () {
    // The landing state: this is what the director sees the moment they click
    // "Returns and debts", before choosing anything.
    Person::factory()->inClass('10a')->create();

    $this->actingAs($this->admin)
        ->get(route('admin.books.returns.form'))
        ->assertOk()
        ->assertSee(__('books.pick_class'));
});

it('opens the issue screen before any class has been picked', function () {
    Person::factory()->inClass('10a')->create();

    $this->actingAs($this->admin)
        ->get(route('admin.books.issue.form'))
        ->assertOk()
        ->assertSee(__('books.pick_class'));
});

it('opens both books screens even when the school has no students yet', function () {
    $this->actingAs($this->admin)->get(route('admin.books.returns.form'))->assertOk();
    $this->actingAs($this->admin)->get(route('admin.books.issue.form'))->assertOk();
});

it('shows no share link until a class is chosen', function () {
    Person::factory()->inClass('10a')->create();

    expect($this->actingAs($this->admin)
        ->get(route('admin.books.returns.form'))
        ->viewData('shareUrl'))->toBeNull();
});
