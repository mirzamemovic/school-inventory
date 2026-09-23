<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Categories are a table, not an enum: schools always add a category nobody
     * predicted. The prefix becomes part of the printed tag and should not be
     * changed once labels exist.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Computer', 'prefix' => 'PC'],
            ['name' => 'Laptop', 'prefix' => 'LAP'],
            ['name' => 'Monitor', 'prefix' => 'MON'],
            ['name' => 'Projector', 'prefix' => 'PRJ'],
            ['name' => 'Printer', 'prefix' => 'PRN'],
            ['name' => 'Network', 'prefix' => 'NET'],
            ['name' => 'Interactive board', 'prefix' => 'IWB'],
            ['name' => 'Lab equipment', 'prefix' => 'LAB'],
            ['name' => 'Fire safety', 'prefix' => 'FIRE', 'needs_inspection_default' => true, 'default_inspection_interval_months' => 12],
            ['name' => 'First aid', 'prefix' => 'AID', 'needs_inspection_default' => true, 'default_inspection_interval_months' => 6],
            ['name' => 'Audio', 'prefix' => 'AUD'],
            ['name' => 'Other', 'prefix' => 'OTH'],
        ];

        foreach ($categories as $category) {
            Category::query()->firstOrCreate(
                ['name' => $category['name']],
                [
                    'prefix' => $category['prefix'],
                    'needs_inspection_default' => $category['needs_inspection_default'] ?? false,
                    'default_inspection_interval_months' => $category['default_inspection_interval_months'] ?? null,
                ],
            );
        }
    }
}
