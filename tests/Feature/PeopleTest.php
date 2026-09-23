<?php

use App\Enums\PersonType;
use App\Models\AssetEvent;
use App\Models\BookLoan;
use App\Models\Person;
use App\Models\User;
use App\Support\AcademicYear;
use App\Support\EventAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

it('adds a person and logs it', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.people.store'), [
            'name' => 'Arben Krasniqi',
            'type' => PersonType::Student->value,
            'class' => '10a',
        ])
        ->assertRedirect();

    expect(Person::query()->where('name', 'Arben Krasniqi')->exists())->toBeTrue()
        ->and(AssetEvent::query()->where('action', EventAction::PERSON_ADDED)->exists())->toBeTrue();
});

it('requires a class for a student but not for staff', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.people.store'), ['name' => 'X', 'type' => PersonType::Student->value])
        ->assertSessionHasErrors('class');

    $this->actingAs($this->admin)
        ->post(route('admin.people.store'), ['name' => 'Y', 'type' => PersonType::Staff->value])
        ->assertRedirect();
});

it('deactivates rather than deletes, so old loans still point somewhere', function () {
    $person = Person::factory()->create();
    $loan = BookLoan::factory()->returned()->create(['person_id' => $person->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.people.deactivate', $person))
        ->assertRedirect();

    expect($person->fresh()->active)->toBeFalse()
        ->and(Person::query()->count())->toBe(1)
        ->and($loan->fresh()->person)->not->toBeNull()
        ->and(AssetEvent::query()->where('action', EventAction::PERSON_DEACTIVATED)->exists())->toBeTrue();
});

it('imports the September list from a spreadsheet', function () {
    $csv = "name,type,class\nArben Krasniqi,student,10a\nDrita Berisha,student,10a\nMr Gashi,staff,\n";

    $this->actingAs($this->admin)
        ->post(route('admin.people.import'), [
            'csv' => UploadedFile::fake()->createWithContent('intake.csv', $csv),
        ])
        ->assertRedirect();

    expect(Person::query()->count())->toBe(3)
        ->and(Person::query()->students()->inClass('10a')->count())->toBe(2)
        ->and(Person::query()->where('type', PersonType::Staff->value)->first()->class)->toBeNull();
});

it('skips people already on the list rather than duplicating them', function () {
    Person::factory()->inClass('10a')->create(['name' => 'Arben Krasniqi']);

    $csv = "Arben Krasniqi,student,10a\nDrita Berisha,student,10a\n";

    $this->actingAs($this->admin)->post(route('admin.people.import'), [
        'csv' => UploadedFile::fake()->createWithContent('intake.csv', $csv),
    ]);

    expect(Person::query()->count())->toBe(2);
});

it('ignores rows it cannot read instead of failing the whole import', function () {
    $csv = "Arben Krasniqi,student,10a\n,,\nSomebody,alien,10a\n";

    $this->actingAs($this->admin)->post(route('admin.people.import'), [
        'csv' => UploadedFile::fake()->createWithContent('intake.csv', $csv),
    ])->assertRedirect();

    expect(Person::query()->count())->toBe(1);
});

it('reads the grade out of the class name', function () {
    expect(Person::factory()->inClass('10a')->make()->grade())->toBe(10)
        ->and(Person::factory()->inClass('9b')->make()->grade())->toBe(9)
        ->and(Person::factory()->staff()->make()->grade())->toBeNull();
});

it('opens a class debt list from a signed link, with no login', function () {
    $student = Person::factory()->inClass('10a')->create(['name' => 'Arben Krasniqi']);
    $loan = BookLoan::factory()->create(['person_id' => $student->id]);

    $url = URL::temporarySignedRoute('class.debts', AcademicYear::endsOn(), ['class' => '10a']);

    $this->get($url)
        ->assertOk()
        ->assertSee('Arben Krasniqi')
        ->assertSee($loan->title->title)
        ->assertSee('noindex, nofollow', escape: false);
});

it('refuses an unsigned or tampered class link', function () {
    Person::factory()->inClass('10a')->create();

    $this->get('/class/10a')->assertForbidden();

    $url = URL::temporarySignedRoute('class.debts', AcademicYear::endsOn(), ['class' => '10a']);

    $this->get(str_replace('10a', '11b', $url))->assertForbidden();
});

it('stops working once the school year is over', function () {
    Person::factory()->inClass('10a')->create();

    $url = URL::temporarySignedRoute('class.debts', AcademicYear::endsOn(), ['class' => '10a']);

    $this->travelTo(AcademicYear::endsOn()->addDay());

    $this->get($url)->assertForbidden();
});

it('shows nothing but book debts on the class link', function () {
    $student = Person::factory()->inClass('10a')->create();
    BookLoan::factory()->create(['person_id' => $student->id]);

    $url = URL::temporarySignedRoute('class.debts', AcademicYear::endsOn(), ['class' => '10a']);

    $this->get($url)
        ->assertOk()
        ->assertDontSee(__('nav.assets'))
        ->assertDontSee(__('nav.manage'))
        ->assertDontSee(__('common.sign_out'));
});

it('leaves students who owe nothing off the class list', function () {
    Person::factory()->inClass('10a')->create(['name' => 'Owes nothing']);

    $url = URL::temporarySignedRoute('class.debts', AcademicYear::endsOn(), ['class' => '10a']);

    $this->get($url)
        ->assertOk()
        ->assertDontSee('Owes nothing')
        ->assertSee(__('books.class_debts_none'));
});
