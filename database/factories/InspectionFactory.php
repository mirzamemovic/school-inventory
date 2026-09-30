<?php

namespace Database\Factories;

use App\Enums\InspectionResult;
use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

class InspectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory()->needingInspection(),
            'inspected_at' => now(),
            'inspector_name' => fake()->name(),
            'result' => InspectionResult::Pass,
            'counted' => true,
        ];
    }

    /** Recorded from a phone that was not set up for safety checks. */
    public function notCounted(): static
    {
        return $this->state(['counted' => false]);
    }

    public function failed(): static
    {
        return $this->state(['result' => InspectionResult::Fail]);
    }
}
