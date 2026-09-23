<?php

namespace App\Services;

use App\Models\Inspection;
use App\Models\Person;
use App\Support\EventAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Erases a person without leaving a hole in the audit log.
 *
 * Nothing here deletes an asset_events row. Gaps in an append-only log are
 * indistinguishable from tampering, and the log is mostly about equipment
 * anyway — removing rows would destroy history about a projector to satisfy a
 * request about a person. Every row stays; the identifying columns inside it
 * are overwritten.
 *
 * That means writing to a table whose model refuses updates. AssetEvent throws
 * AuditLogIsAppendOnly on any update or delete, and that guard stays exactly as
 * it is for every other code path. This class goes through the query builder
 * instead, which bypasses model events — one deliberate, documented exception
 * that can only ever rewrite columns, never remove a row.
 */
class PersonEraser
{
    public function __construct(private readonly AuditLogger $log) {}

    public function pseudonymFor(Person $person): string
    {
        return __('retention.removed_person', ['id' => $person->getKey()]);
    }

    /**
     * Inspections carry a free-text inspector name with no foreign key, so the
     * only way to find them is an exact string match. That is not airtight —
     * two staff sharing a surname, or "A. Krasniqi" against "Arben Krasniqi" —
     * so the command shows these rows to the operator before anything happens.
     *
     * @return Collection<int, Inspection>
     */
    public function inspectionsMatching(Person $person): Collection
    {
        return Inspection::query()
            ->with('asset')
            ->where('inspector_name', $person->name)
            ->orderBy('inspected_at')
            ->get();
    }

    /** @return array<string, int> what was rewritten, for the operator to read back */
    public function erase(Person $person): array
    {
        $name = $person->name;
        $pseudonym = $this->pseudonymFor($person);
        $deviceTokens = $this->deviceTokensOf($person, $name);

        return DB::transaction(function () use ($person, $name, $pseudonym, $deviceTokens) {
            $counts = [
                'person' => 1,
                'reports' => $this->scrubReports($name, $pseudonym),
                'inspections' => $this->scrubInspections($name, $pseudonym),
                'events_actor' => $this->scrubEventActors($name, $pseudonym),
                'events_meta' => $this->scrubEventMeta($name, $pseudonym),
                'identifiers' => $this->nullIdentifiers($deviceTokens),
                'loans_kept' => $person->loans()->count(),
            ];

            $person->forceFill([
                'name' => $pseudonym,
                'email' => null,
                'active' => false,
            ])->save();

            // Named nowhere. The event records that an erasure happened, not
            // who it was about beyond the id already in subject_id.
            $this->log->record($person, EventAction::PERSON_ERASED, [
                'erased_at' => now()->toDateTimeString(),
                'reports_scrubbed' => $counts['reports'],
                'inspections_scrubbed' => $counts['inspections'],
                'events_rewritten' => $counts['events_actor'] + $counts['events_meta'],
                'identifiers_cleared' => $counts['identifiers'],
                'loans_kept' => $counts['loans_kept'],
            ]);

            return $counts;
        });
    }

    /**
     * Every device token this person's actions were recorded under. A token
     * singles out an individual in the same way a cookie id does, so it is
     * personal data and goes with them.
     *
     * @return array<int, string>
     */
    private function deviceTokensOf(Person $person, string $name): array
    {
        return collect()
            ->merge(DB::table('reports')->where('reporter_name', $name)->pluck('device_token'))
            ->merge(DB::table('asset_events')->where('actor_name', $name)->pluck('device_token'))
            ->merge(DB::table('inspections')->where('inspector_name', $name)->pluck('device_token'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function scrubReports(string $name, string $pseudonym): int
    {
        return DB::table('reports')
            ->where('reporter_name', $name)
            ->update(['reporter_name' => $pseudonym]);
    }

    private function scrubInspections(string $name, string $pseudonym): int
    {
        return DB::table('inspections')
            ->where('inspector_name', $name)
            ->update(['inspector_name' => $pseudonym]);
    }

    private function scrubEventActors(string $name, string $pseudonym): int
    {
        return DB::table('asset_events')
            ->where('actor_name', $name)
            ->update(['actor_name' => $pseudonym, 'actor_id' => null]);
    }

    /**
     * The part that is easy to miss. A book-issue or person.added event stores
     * the name inside the meta JSON, and replacing actor_name alone leaves it
     * sitting there in plain text.
     *
     * Matching is on the value, never the key. Keying on "name" would null the
     * name of an asset in asset.created and of a licence in licence.created,
     * destroying equipment history to satisfy a request about a person.
     */
    private function scrubEventMeta(string $name, string $pseudonym): int
    {
        $rewritten = 0;

        DB::table('asset_events')
            ->whereNotNull('meta')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($name, $pseudonym, &$rewritten) {
                foreach ($rows as $row) {
                    $meta = json_decode((string) $row->meta, true);

                    if (! is_array($meta)) {
                        continue;
                    }

                    $scrubbed = $this->replaceInValues($meta, $name, $pseudonym);

                    if ($scrubbed === $meta) {
                        continue;
                    }

                    DB::table('asset_events')
                        ->where('id', $row->id)
                        ->update(['meta' => json_encode($scrubbed)]);

                    $rewritten++;
                }
            });

        return $rewritten;
    }

    /**
     * Walk the structure and replace any string value equal to the name.
     * Compared trimmed and case-insensitively, so "ana b" and " Ana B " both go.
     *
     * @param  array<mixed>  $meta
     * @return array<mixed>
     */
    private function replaceInValues(array $meta, string $name, string $pseudonym): array
    {
        $needle = mb_strtolower(trim($name));

        foreach ($meta as $key => $value) {
            if (is_array($value)) {
                $meta[$key] = $this->replaceInValues($value, $name, $pseudonym);

                continue;
            }

            if (is_string($value) && mb_strtolower(trim($value)) === $needle) {
                $meta[$key] = $pseudonym;
            }
        }

        return $meta;
    }

    /**
     * Device tokens and IP addresses both single out an individual. Leaving
     * either behind keeps every action by that person linked together, and
     * makes them re-identifiable if they use the same phone again.
     *
     * A block list entry keyed to the same token is left alone: blocking a
     * device is a control on a device, with its own justification, not a record
     * about a person.
     *
     * @param  array<int, string>  $deviceTokens
     */
    private function nullIdentifiers(array $deviceTokens): int
    {
        if ($deviceTokens === []) {
            return 0;
        }

        $cleared = DB::table('asset_events')
            ->whereIn('device_token', $deviceTokens)
            ->update(['device_token' => null, 'ip_address' => null]);

        $cleared += DB::table('reports')
            ->whereIn('device_token', $deviceTokens)
            ->update(['device_token' => null, 'ip_address' => null]);

        $cleared += DB::table('inspections')
            ->whereIn('device_token', $deviceTokens)
            ->update(['device_token' => null]);

        return $cleared;
    }
}
