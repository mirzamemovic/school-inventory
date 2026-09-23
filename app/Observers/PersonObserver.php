<?php

namespace App\Observers;

use App\Models\Person;

/**
 * Keeps `left_at` in step with `active` so nobody has to remember it.
 *
 * Deactivating somebody stamps the day they left; making them active again
 * clears it, because they have not left.
 */
class PersonObserver
{
    public function creating(Person $person): void
    {
        // Somebody imported as already gone still has a leaving date.
        if ($person->active === false && $person->left_at === null) {
            $person->left_at = now()->toDateString();
        }
    }

    public function updating(Person $person): void
    {
        if (! $person->isDirty('active')) {
            return;
        }

        // Overwritten rather than kept: if somebody leaves, returns and leaves
        // again, the retention clock runs from the most recent departure.
        $person->left_at = $person->active ? null : now()->toDateString();
    }
}
