<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BookTitleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => ucfirst(fake()->unique()->words(3, true)),
            'subject' => fake()->randomElement(['Mathematics', 'Albanian', 'Physics', 'History']),
            'grade' => 10,
            'total_copies' => 40,
        ];
    }

    public function forGrade(int $grade): static
    {
        return $this->state(['grade' => $grade]);
    }
}
