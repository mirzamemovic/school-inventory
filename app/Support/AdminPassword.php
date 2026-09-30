<?php

namespace App\Support;

use App\Models\Setting;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * The password rule for the two admin accounts. With no second factor and no
 * email reset, the director's password is the entire admin perimeter
 * (spec section 13).
 *
 * Length and a breach check, not "one capital, one digit": composition rules
 * produce Gjimnazi2026, which passes them and falls to the first guess anyone
 * local would make. A phrase of four ordinary words is both longer and easier
 * to remember. The breach check sends only the first five characters of a
 * hash, never the password, and lets the password through if the service
 * cannot be reached, so a server with no internet can still set one.
 */
final class AdminPassword
{
    public const MIN_LENGTH = 16;

    /** Words anyone guessing at this school would try first. */
    private const OBVIOUS = ['password', 'fjalekalim', 'inventory', 'inventar', 'school', 'shkoll', 'gjimnaz', 'qwerty', '123456'];

    /** @return array<int, mixed> */
    public static function rules(?string $email = null): array
    {
        return [
            'required',
            'string',
            Password::min(self::MIN_LENGTH)->uncompromised(),
            function (string $attribute, mixed $value, Closure $fail) use ($email) {
                if (self::containsSomethingObvious((string) $value, $email)) {
                    $fail(__('auth.password_obvious'));
                }
            },
        ];
    }

    private static function containsSomethingObvious(string $password, ?string $email): bool
    {
        $haystack = Str::lower($password);

        $words = collect(self::OBVIOUS)
            ->merge(preg_split('/[^\p{L}\p{N}]+/u', Str::lower(Setting::schoolName())) ?: [])
            ->push($email ? Str::lower(Str::before($email, '@')) : null)
            ->filter(fn ($word) => is_string($word) && mb_strlen($word) >= 4);

        return $words->contains(fn (string $word) => str_contains($haystack, $word));
    }
}
