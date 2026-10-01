<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * An account that was switched off, or whose password was reset, is turned
 * away on its very next request, even mid-session. Deactivating and resetting
 * also delete its sessions, but only the database session driver allows that;
 * this holds whatever the host's driver is.
 */
class EnsureAccountCanSignIn
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->canSignIn()) {
            return $next($request);
        }

        $reason = $user->isDeactivated() ? 'auth.deactivated' : 'auth.password_was_reset';

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // An htmx request would otherwise swap the sign-in page into a corner
        // of the screen it came from.
        if ($request->header('HX-Request')) {
            return response('', 204)->header('HX-Redirect', route('login'));
        }

        return redirect()->route('login')->withErrors(['email' => __($reason)]);
    }
}
