<?php

namespace Database\Factories;

use App\Enums\PersonType;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'type' => PersonType::Student,
            'class' => '10a',
            'active' => true,
        ];
    }

    public function staff(): static
    {
        return $this->state(['type' => PersonType::Staff, 'class' => null]);
    }

    public function inClass(string $class): static
    {
        return $this->state(['type' => PersonType::Student, 'class' => $class]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
