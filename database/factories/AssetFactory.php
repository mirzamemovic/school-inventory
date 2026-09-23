<?php

namespace Database\Factories;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\FundingSource;
use App\Models\Category;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

class AssetFactory extends Factory
{
    public function definition(): array
    {
        $sequence = $this->nextSequence();

        return [
            'asset_tag' => sprintf('INV-TST-%04d', $sequence),
            'tag_sequence' => $sequence,
            'category_id' => Category::factory(),
            'room_id' => Room::factory(),
            'name' => fake()->randomElement(['Projector', 'Desktop PC', 'Laptop', 'Printer']),
            'make' => fake()->randomElement(['Epson', 'Dell', 'HP', 'Lenovo']),
            'model' => strtoupper(fake()->bothify('??-###')),
            'status' => AssetStatus::InUse,
            'condition' => AssetCondition::Good,
            'funding_source' => FundingSource::Budget,
        ];
    }

    /** A pre-printed label that has not been stuck to anything yet. */
    public function blank(): static
    {
        return $this->state([
            'category_id' => null,
            'room_id' => null,
            'name' => null,
            'make' => null,
            'model' => null,
            'status' => AssetStatus::Unassigned,
        ]);
    }

    public function underRepair(): static
    {
        return $this->state([
            'status' => AssetStatus::UnderRepair,
            'condition' => AssetCondition::Broken,
        ]);
    }

    public function needingInspection(int $months = 12): static
    {
        return $this->state([
            'needs_inspection' => true,
            'inspection_interval_months' => $months,
        ]);
    }

    private function nextSequence(): int
    {
        static $sequence = 0;

        $highest = (int) DB::table('assets')->max('tag_sequence');

        return $sequence = max($sequence, $highest) + 1;
    }
}
