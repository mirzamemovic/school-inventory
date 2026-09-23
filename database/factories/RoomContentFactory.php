<?php

namespace Database\Factories;

use App\Enums\AssetCondition;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomContentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'item_name' => fake()->randomElement(['Student desk', 'Chair', 'Cupboard', 'Whiteboard']),
            'quantity' => fake()->numberBetween(1, 30),
            'condition' => AssetCondition::Good,
        ];
    }
}
