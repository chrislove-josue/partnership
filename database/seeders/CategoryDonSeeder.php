<?php

namespace Database\Seeders;

use App\Models\CategoryDon;
use Illuminate\Database\Seeder;

class CategoryDonSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Tithe', 'color' => '#10B981'],
            ['name' => 'Offering', 'color' => '#3B82F6'],
            ['name' => 'Rhapsody of Realities', 'color' => '#F59E0B'],
            ['name' => 'InnerCity Mission', 'color' => '#06B6D4'],
            ['name' => 'Healing Streams', 'color' => '#8B5CF6'],
        ];

        foreach ($categories as $category) {
            CategoryDon::updateOrCreate(
                ['name' => $category['name']],
                ['color' => $category['color']],
            );
        }
    }
}
