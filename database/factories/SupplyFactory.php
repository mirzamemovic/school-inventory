<?php

namespace Database\Factories;

use App\Enums\SupplyUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['A4 paper', 'Black toner', 'White chalk', 'Board markers']),
            'unit' => SupplyUnit::Reams,
            'quantity' => 20,
            'minimum_level' => 5,
        ];
    }

    public function low(): static
    {
        return $this->state(['quantity' => 2, 'minimum_level' => 5]);
    }
}
