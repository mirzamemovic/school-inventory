<?php

namespace App\Services;

use App\Models\BookLoan;
use App\Models\Person;
use App\Support\AcademicYear;
use Illuminate\Database\Eloquent\Collection;

/**
 * What has reached the end of its retention period.
 *
 * This class only ever answers questions. Nothing here deletes anything — the
 * command does that, after showing the operator exactly what it found and
 * asking. Keeping the decision separate from the destruction is what makes the
 * dry run trustworthy: it is the same code path, minus the last step.
 */
class RetentionPolicy
{
    /**
     * Returned loans die at the end of the academic year after the one they
     * were issued in. A book issued in 2024/2025 and given back is kept through
     * the whole of 2025/2026 and removed once that year ends.
     *
     * An open loan is never included, whatever its age. An unreturned book is a
     * live debt, and the record of it is the only thing that says so.
     *
     * @return Collection<int, BookLoan>
     */
    public function expiredLoans(): Collection
    {
        return BookLoan::query()
            ->with(['person', 'title'])
            ->whereNotNull('returned_at')
            ->orderBy('academic_year')
            ->get()
            ->filter(fn (BookLoan $loan) => $this->loanHasExpired($loan))
            ->values();
    }

    public function loanHasExpired(BookLoan $loan): bool
    {
        if ($loan->returned_at === null) {
            return false;
        }

        return AcademicYear::endsOn($this->yearAfter($loan->academic_year))->isPast();
    }

    /** "2024/2025" becomes "2025/2026". */
    public function yearAfter(string $academicYear): string
    {
        $start = (int) explode('/', $academicYear)[0];

        return ($start + 1).'/'.($start + 2);
    }

    /**
     * People whose leaving date is more than a year old.
     *
     * Read from the `left_at` column, never from the audit log: a deletion rule
     * that depends on log completeness fails silently and invisibly when an
     * event is missing.
     *
     * @return Collection<int, Person>
     */
    public function purgeablePeople(): Collection
    {
        return Person::query()
            ->withCount([
                'loans',
                'loans as open_loans_count' => fn ($query) => $query->whereNull('returned_at'),
            ])
            ->whereNotNull('left_at')
            ->where('left_at', '<=', now()->subYear()->toDateString())
            ->orderBy('left_at')
            ->get();
    }

    /**
     * Long gone, and holding nothing. Deleting the person cascades to their
     * loans, so anybody still holding a book is left alone rather than having
     * an open loan quietly deleted underneath them.
     *
     * @return Collection<int, Person>
     */
    public function peopleToDelete(): Collection
    {
        return $this->purgeablePeople()->filter(fn (Person $person) => $person->open_loans_count === 0)->values();
    }

    /**
     * Past their retention date but still holding a book. Reported, never
     * deleted.
     *
     * @return Collection<int, Person>
     */
    public function peopleHeldBack(): Collection
    {
        return $this->purgeablePeople()->filter(fn (Person $person) => $person->open_loans_count > 0)->values();
    }
}
