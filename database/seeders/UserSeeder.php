<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Church;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $groupChurch = Church::where('name', 'CE SIKECODJI')->firstOrFail();

        $superAdmin = User::updateOrCreate(
            ['email' => 'libertsinzo7@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('azerty123'),
                'assigned_church_id' => $groupChurch->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $superAdmin->syncRoles([RoleName::SUPER_ADMIN->value]);
    }
}
