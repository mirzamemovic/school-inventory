<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * A signed-in admin typing their own password again, before account
 * management or a password change. Wrong attempts are limited per account the
 * same way sign-in is, or a session left open on a shared computer would be a
 * way to guess the password at leisure.
 */
final class PasswordRecheck
{
    public const ATTEMPTS_PER_MINUTE = 5;

    public static function verify(Request $request, string $field): void
    {
        $user = $request->user();
        $key = 'password-recheck:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, self::ATTEMPTS_PER_MINUTE)) {
            throw ValidationException::withMessages([
                $field => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        if (! Hash::check((string) $request->input($field), $user->password)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([$field => __('users.password.wrong')]);
        }

        RateLimiter::clear($key);
    }
}
