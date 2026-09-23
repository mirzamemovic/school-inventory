<?php

namespace Database\Factories;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\ReporterType;
use App\Enums\ReportStatus;
use App\Models\Asset;
use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => Report::newReference(),
            'asset_id' => Asset::factory(),
            'reporter_name' => fake()->name(),
            'reporter_type' => ReporterType::Staff,
            'device_token' => fake()->sha1(),
            'description' => 'It does not turn on.',
            'status' => ReportStatus::Open,
            'asset_status_before' => AssetStatus::InUse,
            'asset_condition_before' => AssetCondition::Good,
        ];
    }

    public function fromStudent(): static
    {
        return $this->state(['reporter_type' => ReporterType::Student]);
    }

    public function inProgress(): static
    {
        return $this->state([
            'status' => ReportStatus::InProgress,
            'acknowledged_at' => now(),
        ]);
    }

    public function resolved(): static
    {
        return $this->state([
            'status' => ReportStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }

    public function reportedDaysAgo(int $days): static
    {
        return $this->state(['created_at' => now()->subDays($days)]);
    }
}
