<?php

namespace App\Support;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\AssetEvent;

/**
 * Each row of history reads as a sentence, not a table of ids
 * (spec section 8).
 */
final class EventSentence
{
    public static function for(AssetEvent $event): string
    {
        $key = 'activity.sentence.'.$event->action;

        $line = __($key, self::replacements($event));

        return $line === $key
            ? __('activity.sentence.fallback', ['actor' => $event->actorLabel()])
            : $line;
    }

    /** @return array<string, string> */
    private static function replacements(AssetEvent $event): array
    {
        $replacements = [
            'actor' => $event->actorLabel(),
            'from' => __('common.not_set'),
            'to' => __('common.not_set'),
            // The account acted on in Manage → Users, as it was named then.
            'account' => self::readable($event->meta['account'] ?? null),
            // Which room, category, supply or title; after a rename, the new name.
            'name' => self::readable(self::current($event->meta['name'] ?? null)),
            // A furniture line within a room.
            'item' => self::readable($event->meta['item'] ?? null),
        ];

        foreach ((array) $event->meta as $field => $value) {
            if (is_array($value) && array_key_exists('from', $value)) {
                $replacements['from'] = self::readable(self::label($field, $value['from']));
                $replacements['to'] = self::readable(self::label($field, $value['to'] ?? null));
                break;
            }
        }

        return $replacements;
    }

    /** The log keeps the stored value; a person reads the word the screens use. */
    private static function label(string|int $field, mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return match ($field) {
            'status' => AssetStatus::tryFrom($value)?->label(),
            'condition' => AssetCondition::tryFrom($value)?->label(),
            default => null,
        } ?? $value;
    }

    /** A plain value, or the "to" side of a before/after pair. */
    private static function current(mixed $value): mixed
    {
        return is_array($value) && array_key_exists('to', $value) ? $value['to'] : $value;
    }

    private static function readable(mixed $value): string
    {
        if ($value === null || $value === '') {
            return __('common.not_set');
        }

        return is_scalar($value) ? (string) $value : json_encode($value);
    }
}
