<?php

namespace Database\Factories;

use App\Models\InspectorPhone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<InspectorPhone> */
class InspectorPhoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'label' => "Caretaker's phone",
            'invite_expires_at' => now()->addMinutes(InspectorPhone::INVITE_MINUTES),
        ];
    }

    /** Already set up on a phone with this device token. */
    public function claimedBy(?string $token = null): static
    {
        return $this->state(fn () => [
            'device_token' => $token ?? Str::random(40),
            'claimed_at' => now(),
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn () => ['withdrawn_at' => now()]);
    }
}
