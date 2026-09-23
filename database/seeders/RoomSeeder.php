<?php

namespace Database\Seeders;

use App\Enums\RoomType;
use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Without a home for genuinely mobile equipment, people invent
     * inconsistent answers for where a laptop lives (spec section 5).
     */
    public function run(): void
    {
        Room::query()->firstOrCreate(
            ['name' => 'Off-site / with staff'],
            ['type' => RoomType::Offsite],
        );
    }
}
