<?php

namespace Database\Seeders;

use App\Models\CategoryDon;
use App\Support\DonationCategoryNames;
use Illuminate\Database\Seeder;

class CategoryDonSeeder extends Seeder
{
    public function run(): void
    {
        $colors = [
            '#10B981',
            '#3B82F6',
            '#F59E0B',
            '#06B6D4',
            '#8B5CF6',
        ];

        $categories = array_map(
            fn (string $name, string $color): array => ['name' => $name, 'color' => $color],
            DonationCategoryNames::CANONICAL,
            $colors,
        );

        foreach ($categories as $category) {
            CategoryDon::updateOrCreate(
                ['name' => $category['name']],
                ['color' => $category['color']],
            );
        }
    }
}
