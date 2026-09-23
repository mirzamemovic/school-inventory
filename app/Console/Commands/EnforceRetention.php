<?php

namespace App\Console\Commands;

use App\Models\BookLoan;
use App\Models\Person;
use App\Services\AuditLogger;
use App\Services\RetentionPolicy;
use App\Support\EventAction;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Run by hand, never on a schedule.
 *
 * Deleting personal records is not something that should happen quietly at
 * three in the morning with nobody watching. Somebody reads what is about to
 * go, and says yes.
 */
class EnforceRetention extends Command
{
    protected $signature = 'data:enforce-retention {--dry-run : List what would go, and change nothing}';

    protected $description = 'Remove book loans and people that have reached the end of their retention period';

    public function handle(RetentionPolicy $policy, AuditLogger $log): int
    {
        $loans = $policy->expiredLoans();
        $people = $policy->peopleToDelete();
        $heldBack = $policy->peopleHeldBack();

        $this->reportLoans($loans);
        $this->reportPeople($people);
        $this->reportHeldBack($heldBack);

        if ($loans->isEmpty() && $people->isEmpty()) {
            $this->info(__('retention.nothing_to_do'));

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info(__('retention.dry_run_notice'));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn(__('retention.about_to_delete', [
            'loans' => $loans->count(),
            'people' => $people->count(),
        ]));

        if (! $this->confirm(__('retention.confirm'), false)) {
            $this->info(__('retention.cancelled'));

            return self::SUCCESS;
        }

        $this->purge($loans, $people, $log);

        $this->newLine();
        $this->info(__('retention.done', [
            'loans' => $loans->count(),
            'people' => $people->count(),
        ]));

        return self::SUCCESS;
    }

    /** @param  Collection<int, BookLoan>  $loans */
    private function reportLoans($loans): void
    {
        $this->newLine();
        $this->line('<options=bold>'.__('retention.heading_loans').'</>');

        if ($loans->isEmpty()) {
            $this->line('  '.__('retention.none'));

            return;
        }

        $this->table(
            [__('retention.col_person'), __('retention.col_title'), __('retention.col_year'), __('retention.col_returned')],
            $loans->map(fn (BookLoan $loan) => [
                $loan->person?->name ?? '—',
                $loan->title?->title ?? '—',
                $loan->academic_year,
                $loan->returned_at?->format('j M Y') ?? '—',
            ])->all(),
        );
    }

    /** @param  Collection<int, Person>  $people */
    private function reportPeople($people): void
    {
        $this->newLine();
        $this->line('<options=bold>'.__('retention.heading_people').'</>');

        if ($people->isEmpty()) {
            $this->line('  '.__('retention.none'));

            return;
        }

        $this->table(
            [__('retention.col_person'), __('retention.col_type'), __('retention.col_left'), __('retention.col_loans')],
            $people->map(fn (Person $person) => [
                $person->name,
                $person->type->label(),
                $person->left_at?->format('j M Y') ?? '—',
                $person->loans_count,
            ])->all(),
        );
    }

    /** @param  Collection<int, Person>  $heldBack */
    private function reportHeldBack($heldBack): void
    {
        if ($heldBack->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->line('<options=bold>'.__('retention.heading_held_back').'</>');
        $this->line('  '.__('retention.held_back_why'));

        $this->table(
            [__('retention.col_person'), __('retention.col_left'), __('retention.col_open_loans')],
            $heldBack->map(fn (Person $person) => [
                $person->name,
                $person->left_at?->format('j M Y') ?? '—',
                $person->open_loans_count,
            ])->all(),
        );
    }

    /**
     * @param  Collection<int, BookLoan>  $loans
     * @param  Collection<int, Person>  $people
     */
    private function purge($loans, $people, AuditLogger $log): void
    {
        DB::transaction(function () use ($loans, $people, $log) {
            // Logged before the person disappears, so the event has a subject
            // id to point at. No name goes into the meta.
            foreach ($people as $person) {
                $log->record($person, EventAction::DATA_RETENTION_ENFORCED, [
                    'person_deleted' => true,
                    'loans_deleted' => $person->loans_count,
                    'left_at' => $person->left_at?->toDateString(),
                ]);
            }

            $loansByPerson = $loans->groupBy('person_id');

            foreach ($loansByPerson as $personId => $personLoans) {
                $owner = Person::query()->find($personId);

                // Their whole record is going anyway; one event covers it.
                if (! $owner || $people->contains('id', $owner->id)) {
                    continue;
                }

                $log->record($owner, EventAction::DATA_RETENTION_ENFORCED, [
                    'person_deleted' => false,
                    'loans_deleted' => $personLoans->count(),
                ]);
            }

            BookLoan::query()->whereIn('id', $loans->pluck('id'))->delete();

            // Cascades to whatever loans they still had, all of them returned.
            Person::query()->whereIn('id', $people->pluck('id'))->delete();
        });
    }
}
