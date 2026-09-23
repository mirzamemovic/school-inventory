<?php

namespace Database\Factories;

use App\Enums\LicenseModel;
use App\Enums\LicenseType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LicenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Microsoft 365', 'ESET Endpoint', 'Adobe Acrobat', 'Zoom Pro']),
            'vendor' => fake()->randomElement(['Microsoft', 'ESET', 'Adobe', 'Zoom']),
            'type' => LicenseType::Software,
            'licence_model' => LicenseModel::Subscription,
            'seats_total' => 25,
            'seats_used' => 18,
            'purchase_date' => now()->subYear()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
            'renewal_cost' => 240.00,
            'auto_renew' => false,
            'account_reference' => 'it@school.example',
        ];
    }

    /** No expiry date at all: the case the expiry list must never call overdue. */
    public function perpetual(): static
    {
        return $this->state([
            'licence_model' => LicenseModel::Perpetual,
            'expires_at' => null,
            'renewal_cost' => null,
        ]);
    }

    public function expiringInDays(int $days): static
    {
        return $this->state(['expires_at' => now()->addDays($days)->toDateString()]);
    }

    public function expired(int $daysAgo = 30): static
    {
        return $this->state(['expires_at' => now()->subDays($daysAgo)->toDateString()]);
    }

    public function autoRenewing(): static
    {
        return $this->state(['auto_renew' => true]);
    }
}
