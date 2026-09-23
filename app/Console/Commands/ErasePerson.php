<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Services\PersonEraser;
use Illuminate\Console\Command;

/**
 * Run by hand, never on a schedule. Erasure happens because somebody asked for
 * it, and a person has to check it is the right person first.
 */
class ErasePerson extends Command
{
    protected $signature = 'person:erase {id : The id of the person to erase}';

    protected $description = 'Replace a person with a pseudonym everywhere, keeping every audit log row';

    public function handle(PersonEraser $eraser): int
    {
        $person = Person::query()->find($this->argument('id'));

        if (! $person) {
            $this->error(__('retention.person_not_found', ['id' => $this->argument('id')]));

            return self::FAILURE;
        }

        $this->line(__('retention.about_to_erase', [
            'name' => $person->name,
            'id' => $person->getKey(),
            'pseudonym' => $eraser->pseudonymFor($person),
        ]));

        $this->reportInspections($eraser, $person);

        $this->newLine();
        $this->line(__('retention.erase_keeps_loans', ['count' => $person->loans()->count()]));
        $this->line(__('retention.erase_keeps_log'));

        if (! $this->confirm(__('retention.confirm_erase'), false)) {
            $this->info(__('retention.cancelled'));

            return self::SUCCESS;
        }

        $counts = $eraser->erase($person);

        $this->newLine();
        $this->info(__('retention.erased', ['id' => $person->getKey()]));
        $this->table([__('retention.col_what'), __('retention.col_count')], [
            [__('retention.row_reports'), $counts['reports']],
            [__('retention.row_inspections'), $counts['inspections']],
            [__('retention.row_events_actor'), $counts['events_actor']],
            [__('retention.row_events_meta'), $counts['events_meta']],
            [__('retention.row_identifiers'), $counts['identifiers']],
            [__('retention.row_loans_kept'), $counts['loans_kept']],
        ]);

        return self::SUCCESS;
    }

    /**
     * Inspector names are free text with no link to a person, so these rows are
     * found by exact string match. The operator sees every one before agreeing,
     * because a silent fuzzy match on destructive work is the wrong design.
     */
    private function reportInspections(PersonEraser $eraser, Person $person): void
    {
        $inspections = $eraser->inspectionsMatching($person);

        $this->newLine();
        $this->line('<options=bold>'.__('retention.heading_inspections').'</>');
        $this->line('  '.__('retention.inspections_caveat'));

        if ($inspections->isEmpty()) {
            $this->line('  '.__('retention.none'));

            return;
        }

        $this->table(
            [__('retention.col_date'), __('retention.col_asset'), __('retention.col_inspector')],
            $inspections->map(fn ($inspection) => [
                $inspection->inspected_at?->format('j M Y') ?? '—',
                $inspection->asset?->displayName().' '.$inspection->asset?->asset_tag,
                $inspection->inspector_name,
            ])->all(),
        );
    }
}
