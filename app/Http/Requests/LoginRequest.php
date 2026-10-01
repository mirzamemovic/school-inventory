<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Five attempts a minute per email address (spec section 13) — counted per
     * email and IP address, so a stranger failing on purpose cannot keep the
     * director locked out from everywhere. A second, looser ceiling per email
     * across all addresses stops slow guessing spread over many machines.
     */
    public const ATTEMPTS_PER_MINUTE = 5;

    public const ATTEMPTS_PER_HOUR_FOR_ACCOUNT = 100;

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // Checked only after the password matched, so saying "switched off"
        // tells nothing to someone who does not already know the password.
        $switchedOff = false;

        $signedIn = Auth::attemptWhen(
            $this->only('email', 'password'),
            function (User $user) use (&$switchedOff) {
                $switchedOff = $user->isDeactivated();

                return $user->canSignIn();
            },
            $this->boolean('remember'),
        );

        if (! $signedIn) {
            RateLimiter::hit($this->throttleKey(), 60);
            RateLimiter::hit($this->accountKey(), 3600);

            throw ValidationException::withMessages([
                'email' => __($switchedOff ? 'auth.deactivated' : 'auth.failed'),
            ]);
        }

        // Only this address's count is forgiven. The account-wide count is
        // left to run out on its own, so a correct login cannot be used to
        // wipe a stranger's guesses.
        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        $here = RateLimiter::tooManyAttempts($this->throttleKey(), self::ATTEMPTS_PER_MINUTE);
        $everywhere = RateLimiter::tooManyAttempts($this->accountKey(), self::ATTEMPTS_PER_HOUR_FOR_ACCOUNT);

        if (! $here && ! $everywhere) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => max(
                    $here ? RateLimiter::availableIn($this->throttleKey()) : 0,
                    $everywhere ? RateLimiter::availableIn($this->accountKey()) : 0,
                ),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip().'|login');
    }

    private function accountKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|login-account');
    }
}
