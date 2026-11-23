<?php

namespace Database\Seeders;

use App\Enums\CategoryGroup;
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
            '#84CC16',
            '#06B6D4',
            '#8B5CF6',
            '#EC4899',
        ];

        $categories = array_map(
            fn (string $name, string $color): array => [
                'name' => $name,
                'color' => $color,
                'group' => CategoryGroup::fromName($name)->value,
            ],
            DonationCategoryNames::CANONICAL,
            $colors,
        );

        foreach ($categories as $category) {
            CategoryDon::updateOrCreate(
                ['name' => $category['name']],
                ['color' => $category['color'], 'group' => $category['group']],
            );
        }
    }
}
