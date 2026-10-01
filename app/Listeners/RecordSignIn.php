<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * "Last signed in" on the Users page, so an account nobody uses — or one used
 * when its owner was away — stands out. Fires for a password sign-in and for a
 * "keep me signed in" return alike.
 *
 * Not written to the audit log: two people signing in every day would bury
 * everything else in it.
 */
class RecordSignIn
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $event->user->forceFill(['last_login_at' => now()])->save();
        }
    }
}
