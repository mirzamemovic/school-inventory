<?php

namespace App\Services;

use App\Enums\LoanCondition;
use App\Models\BookLoan;
use App\Models\BookTitle;
use App\Models\Person;
use App\Support\AcademicYear;
use App\Support\EventAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BookService
{
    public function __construct(private readonly AuditLogger $log) {}

    /**
     * Issue a set of titles to a set of students in one go. This is the whole
     * of September: pick a class, confirm, done (spec section 7C).
     *
     * @param  array<int, array<int, int>>  $selection  person id => list of title ids
     * @return int how many loans were actually created
     */
    public function issue(array $selection): int
    {
        $year = AcademicYear::current();
        $due = AcademicYear::endsOn($year)->toDateString();
        $today = now()->toDateString();

        return DB::transaction(function () use ($selection, $year, $due, $today) {
            $issued = 0;

            foreach ($selection as $personId => $titleIds) {
                $person = Person::query()->find($personId);

                if (! $person) {
                    continue;
                }

                // Somebody already holding a copy does not get a second one,
                // so re-running the issue for a class is safe.
                $alreadyHeld = $person->outstandingLoans()->pluck('book_title_id')->all();
                $toIssue = array_diff(array_map('intval', $titleIds), $alreadyHeld);

                foreach ($toIssue as $titleId) {
                    BookLoan::query()->create([
                        'person_id' => $person->id,
                        'book_title_id' => $titleId,
                        'issued_at' => $today,
                        'due_at' => $due,
                        'academic_year' => $year,
                    ]);

                    $issued++;
                }

                if ($toIssue !== []) {
                    $this->log->record($person, EventAction::BOOK_ISSUED, [
                        'titles' => count($toIssue),
                        'academic_year' => $year,
                    ]);
                }
            }

            return $issued;
        });
    }

    /**
     * @param  array<int, string>  $conditions  loan id => condition
     * @return int how many loans were closed
     */
    public function takeBack(array $conditions): int
    {
        return DB::transaction(function () use ($conditions) {
            $returned = 0;
            $byPerson = [];

            foreach ($conditions as $loanId => $condition) {
                $loan = BookLoan::query()->with('person')->find($loanId);

                if (! $loan || $loan->returned_at !== null) {
                    continue;
                }

                $loan->update([
                    'returned_at' => now()->toDateString(),
                    'condition_on_return' => LoanCondition::from($condition),
                ]);

                $byPerson[$loan->person_id] = ($byPerson[$loan->person_id] ?? 0) + 1;
                $returned++;
            }

            foreach ($byPerson as $personId => $count) {
                $person = Person::query()->find($personId);

                if ($person) {
                    $this->log->record($person, EventAction::BOOK_RETURNED, ['titles' => $count]);
                }
            }

            return $returned;
        });
    }

    /**
     * The Grade 10 set is every title where grade = 10. There is no separate
     * set table to keep in step (spec section 5).
     *
     * @return Collection<int, BookTitle>
     */
    public function titlesForGrade(?int $grade): Collection
    {
        return $grade === null
            ? collect()
            : BookTitle::query()->where('grade', $grade)->orderBy('subject')->orderBy('title')->get();
    }

    /** @return Collection<int, string> */
    public function classes(): Collection
    {
        return Person::query()
            ->students()
            ->active()
            ->whereNotNull('class')
            ->distinct()
            ->orderBy('class')
            ->pluck('class');
    }
}
