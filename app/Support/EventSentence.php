<?php

namespace App\Support;

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
        ];

        foreach ((array) $event->meta as $value) {
            if (is_array($value) && array_key_exists('from', $value)) {
                $replacements['from'] = self::readable($value['from']);
                $replacements['to'] = self::readable($value['to'] ?? null);
                break;
            }
        }

        return $replacements;
    }

    private static function readable(mixed $value): string
    {
        if ($value === null || $value === '') {
            return __('common.not_set');
        }

        return is_scalar($value) ? (string) $value : json_encode($value);
    }
}
