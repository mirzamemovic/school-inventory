<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'needs_inspection_default' => false,
            'default_inspection_interval_months' => null,
        ];
    }

    public function inspected(int $months = 12): static
    {
        return $this->state([
            'needs_inspection_default' => true,
            'default_inspection_interval_months' => $months,
        ]);
    }
}
