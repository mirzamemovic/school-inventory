<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Collection;

/**
 * Finds another asset already carrying the same serial number.
 *
 * This warns and never blocks. Two things make a hard uniqueness rule wrong
 * here: most items arrive with no serial at all, and vendors genuinely reuse
 * serials across product lines. A block would leave the person holding the
 * laptop unable to record it, with no way to overrule the system.
 */
class SerialNumberCheck
{
    /** @param  int|null  $exceptId  the asset being edited, which is not its own duplicate */
    public function duplicateOf(?string $serial, ?int $exceptId = null): ?Asset
    {
        return $this->duplicatesOf($serial, $exceptId)->first();
    }

    /**
     * @return Collection<int, Asset>
     */
    public function duplicatesOf(?string $serial, ?int $exceptId = null)
    {
        $serial = trim((string) $serial);

        if ($serial === '') {
            return Asset::query()->whereRaw('1 = 0')->get();
        }

        return Asset::query()
            ->with('room')
            // Compared case-insensitively and trimmed: somebody typing
            // "sn-4471-xq" has found the same machine as "SN-4471-XQ".
            // lower() rather than a collation, so SQLite and MySQL agree.
            ->whereRaw('lower(trim(serial_number)) = ?', [mb_strtolower($serial)])
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->orderBy('tag_sequence')
            ->get();
    }
}
