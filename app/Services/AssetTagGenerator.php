<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Category;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Tags are sequential and global, so no two assets can collide even across
 * categories. The sequence is stored as an integer alongside the printed tag so
 * the next number never has to be parsed back out of a string.
 */
class AssetTagGenerator
{
    private const MAX_ATTEMPTS = 5;

    public function format(?Category $category, int $sequence): string
    {
        $prefix = $category?->prefix ?: config('school.blank_tag_prefix');

        return sprintf('%s-%s-%04d', config('school.tag_prefix'), $prefix, $sequence);
    }

    /**
     * Fill in the tag on an unsaved asset and persist it, retrying if two
     * people happen to save at the same moment.
     */
    public function assignAndSave(Asset $asset): Asset
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $sequence = $this->nextSequence();

            $asset->asset_tag = $this->format($asset->category, $sequence);
            $asset->tag_sequence = $sequence;

            try {
                $asset->save();

                return $asset;
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt === self::MAX_ATTEMPTS) {
                    throw $e;
                }
            }
        }

        return $asset;
    }

    public function nextSequence(): int
    {
        return (int) DB::table('assets')->max('tag_sequence') + 1;
    }
}
