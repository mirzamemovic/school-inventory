<?php

namespace App\Services;

use App\Models\AssetEvent;
use App\Support\Actor;
use Illuminate\Database\Eloquent\Model;

/**
 * Every write path in the application writes here. No exceptions
 * (spec section 5).
 */
class AuditLogger
{
    /** @param  array<string, mixed>  $meta */
    public function record(Model $subject, string $action, array $meta = [], ?Actor $actor = null): AssetEvent
    {
        $actor ??= Actor::current();

        return AssetEvent::create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'device_token' => $actor->deviceToken,
            'ip_address' => request()?->ip(),
            'meta' => $meta ?: null,
        ]);
    }

    /**
     * Build the before/after meta the log is actually useful with. A change of
     * "room from Lab 2 to Room 14" is worth storing; "asset edited" is not.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, mixed>
     */
    public function changes(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $field => $value) {
            $was = $before[$field] ?? null;

            if ($was != $value) {
                $changes[$field] = ['from' => $was, 'to' => $value];
            }
        }

        return $changes;
    }

    /**
     * Log an edit, but only if something actually changed. `$name` says which
     * record it was, for the sentence in Activity; if the name itself is one
     * of the changes, its before/after pair is kept instead.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function recordChanges(Model $subject, string $action, array $before, array $after, ?string $name = null): ?AssetEvent
    {
        $changes = $this->changes($before, $after);

        if ($changes === []) {
            return null;
        }

        return $this->record($subject, $action, $changes + ($name === null ? [] : ['name' => $name]));
    }
}
