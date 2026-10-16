<?php

namespace Database\Seeders;

use App\Enums\ChurchType;
use App\Models\Church;
use Illuminate\Database\Seeder;

class ChurchSeeder extends Seeder
{
    public function run(): void
    {
        Church::updateOrCreate(
            ['name' => 'CE SIKECODJI'],
            ['type' => ChurchType::GROUP],
        );
    }
}
