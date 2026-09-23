<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\BookTitle;
use App\Models\License;
use App\Models\Person;
use App\Models\Report;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * One box that looks in every table the director might be thinking of.
 *
 * Plain LIKE queries and nothing else: no Scout, no index, no full-text engine.
 * At a hundred assets and a few hundred people the whole search is six small
 * queries and finishes long before the network does.
 *
 * Everything here reads across the admin side of the system, including serial
 * numbers and report references. It must only ever be reached from behind the
 * password (spec section 13).
 */
class GlobalSearch
{
    /** Below this a search matches half the school and helps nobody. */
    public const MIN_TERM_LENGTH = 2;

    private const PER_GROUP = 6;

    /**
     * @return Collection<int, array{
     *     key: string,
     *     heading: string,
     *     items: Collection<int, array{title: string, subtitle: ?string, meta: ?string, url: string}>
     * }>
     */
    public function groups(?string $term): Collection
    {
        $term = trim((string) $term);

        if (mb_strlen($term) < self::MIN_TERM_LENGTH) {
            return collect();
        }

        return collect([
            $this->group('assets', $this->assets($term)),
            $this->group('people', $this->people($term)),
            $this->group('rooms', $this->rooms($term)),
            $this->group('licenses', $this->licenses($term)),
            $this->group('books', $this->bookTitles($term)),
            $this->group('reports', $this->reports($term)),
        ])->filter(fn (array $group) => $group['items']->isNotEmpty())->values();
    }

    public function total(?string $term): int
    {
        return $this->groups($term)->sum(fn (array $group) => $group['items']->count());
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function group(string $key, Collection $items): array
    {
        return [
            'key' => $key,
            'heading' => __('search.groups.'.$key),
            'items' => $items,
        ];
    }

    private function assets(string $term): Collection
    {
        return Asset::query()
            ->with('room')
            ->tap(fn (Builder $query) => $this->whereAnyLike(
                $query, ['asset_tag', 'name', 'make', 'model', 'serial_number'], $term
            ))
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Asset $asset) => [
                'title' => $asset->displayName(),
                'subtitle' => collect([$asset->make, $asset->model, $asset->room?->name])->filter()->join(' · ') ?: null,
                'meta' => $asset->asset_tag,
                'url' => route('admin.assets.show', $asset),
            ]);
    }

    private function people(string $term): Collection
    {
        return Person::query()
            ->tap(fn (Builder $query) => $this->whereAnyLike($query, ['name'], $term))
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Person $person) => [
                'title' => $person->name,
                'subtitle' => collect([$person->type->label(), $person->class])->filter()->join(' · ') ?: null,
                'meta' => $person->active ? null : __('people.inactive'),
                'url' => route('admin.books.student', $person),
            ]);
    }

    private function rooms(string $term): Collection
    {
        return Room::query()
            ->tap(fn (Builder $query) => $this->whereAnyLike($query, ['name'], $term))
            ->withCount('assets')
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Room $room) => [
                'title' => $room->name,
                'subtitle' => collect([$room->type->label(), $room->floor])->filter()->join(' · ') ?: null,
                'meta' => trans_choice('rooms.asset_count', $room->assets_count, ['count' => $room->assets_count]),
                'url' => route('admin.rooms.show', $room),
            ]);
    }

    private function licenses(string $term): Collection
    {
        return License::query()
            ->tap(fn (Builder $query) => $this->whereAnyLike($query, ['name', 'vendor'], $term))
            ->byExpiry()
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (License $license) => [
                'title' => $license->name,
                'subtitle' => $license->vendor,
                'meta' => $license->neverExpires()
                    ? __('licenses.never_expires')
                    : $license->expires_at->format('j M Y'),
                'url' => route('admin.licenses.show', $license),
            ]);
    }

    private function bookTitles(string $term): Collection
    {
        return BookTitle::query()
            ->tap(fn (Builder $query) => $this->whereAnyLike($query, ['title'], $term))
            ->orderBy('title')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (BookTitle $title) => [
                'title' => $title->title,
                'subtitle' => $title->subject,
                'meta' => __('books.grade', ['grade' => $title->grade]),
                'url' => route('admin.books.titles.edit', $title),
            ]);
    }

    /**
     * Reference numbers only. Somebody rings up quoting the code on their
     * confirmation screen, and this is what turns it back into a report.
     */
    private function reports(string $term): Collection
    {
        return Report::query()
            ->with('asset')
            ->tap(fn (Builder $query) => $this->whereAnyLike($query, ['reference'], $term))
            ->latest('created_at')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Report $report) => [
                'title' => $report->reference,
                'subtitle' => $report->asset?->displayName(),
                'meta' => $report->status->label(),
                'url' => route('admin.reports.show', $report),
            ]);
    }

    /**
     * Match any of the given columns against the term.
     *
     * Raw LIKE with an explicit ESCAPE clause, because the two databases
     * disagree otherwise: MySQL treats a backslash as an escape character
     * inside LIKE and SQLite does not, so the same query would behave one way
     * in development and another in production. An exclamation mark is an
     * ordinary character to both.
     *
     * Column names come from the fixed lists above, never from input.
     *
     * @param  array<int, string>  $columns
     */
    private function whereAnyLike(Builder $query, array $columns, string $term): Builder
    {
        $pattern = $this->wildcard($term);

        return $query->where(function (Builder $inner) use ($columns, $pattern) {
            foreach ($columns as $column) {
                $inner->orWhereRaw("{$column} like ? escape '!'", [$pattern]);
            }
        });
    }

    /**
     * Escape the characters LIKE treats as wildcards, so somebody searching for
     * "50%" gets rows containing "50%" rather than every row in the table.
     */
    private function wildcard(string $term): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
    }
}
