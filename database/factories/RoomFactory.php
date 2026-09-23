<?php

namespace Database\Factories;

use App\Enums\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Room '.fake()->unique()->numberBetween(1, 400),
            'floor' => fake()->randomElement(['Ground', 'First', 'Second']),
            'type' => RoomType::Classroom,
        ];
    }

    public function offsite(): static
    {
        return $this->state(['name' => 'Off-site / with staff', 'type' => RoomType::Offsite]);
    }
}
