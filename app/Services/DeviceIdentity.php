<?php

namespace App\Services;

use App\Enums\ReporterType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Who the person filling in a public form says they are, remembered on their
 * device for a year so they are only asked once.
 *
 * This cookie is a convenience, not a credential. It must never unlock
 * anything, grant any capability, or be checked as a permission. Someone who
 * copies another person's cookie gains only the ability to file a report under
 * a different name — which they could already do by typing a different name
 * (spec section 3.1).
 */
class DeviceIdentity
{
    private const COOKIE = 'si_reporter';

    private const REQUEST_KEY = 'device_token';

    private const ONE_YEAR_IN_MINUTES = 525600;

    /**
     * The token for this request.
     *
     * A first-time visitor has no cookie, so one is minted here — and then
     * remembered on the request, because the rate limiter and the write path
     * both ask for it and they must get the same answer. Minting a fresh token
     * per call would give every request its own rate limit bucket.
     */
    public function token(Request $request): string
    {
        if ($request->attributes->has(self::REQUEST_KEY)) {
            return $request->attributes->get(self::REQUEST_KEY);
        }

        $stored = $this->decode($request)['token'] ?? null;
        $token = is_string($stored) && $stored !== '' ? $stored : $this->newToken();

        $request->attributes->set(self::REQUEST_KEY, $token);

        return $token;
    }

    public function name(Request $request): ?string
    {
        $name = $this->decode($request)['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    public function type(Request $request): ?ReporterType
    {
        return ReporterType::tryFrom((string) ($this->decode($request)['type'] ?? ''));
    }

    /** Has this device told us a name before? */
    public function isKnown(Request $request): bool
    {
        return $this->name($request) !== null;
    }

    public function remember(string $token, string $name, ReporterType $type): void
    {
        Cookie::queue(
            self::COOKIE,
            json_encode(['token' => $token, 'name' => $name, 'type' => $type->value]),
            self::ONE_YEAR_IN_MINUTES,
        );
    }

    public function newToken(): string
    {
        return Str::random(40);
    }

    /** @return array<string, mixed> */
    private function decode(Request $request): array
    {
        $raw = $request->cookie(self::COOKIE);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
