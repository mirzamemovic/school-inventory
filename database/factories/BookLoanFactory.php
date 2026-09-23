<?php

namespace Database\Factories;

use App\Models\BookTitle;
use App\Models\Person;
use App\Support\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookLoanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'book_title_id' => BookTitle::factory(),
            'issued_at' => now()->toDateString(),
            'due_at' => AcademicYear::endsOn()->toDateString(),
            'academic_year' => AcademicYear::current(),
        ];
    }

    public function overdue(): static
    {
        return $this->state([
            'issued_at' => now()->subYear()->toDateString(),
            'due_at' => now()->subMonth()->toDateString(),
        ]);
    }

    public function returned(): static
    {
        return $this->state([
            'returned_at' => now()->toDateString(),
            'condition_on_return' => 'good',
        ]);
    }
}
