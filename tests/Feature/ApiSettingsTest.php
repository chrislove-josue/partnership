<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Pages\ApiSettings;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seul_le_super_administrateur_peut_lire_l_adresse_api(): void
    {
        $this->seed();

        $admin = User::where('email', 'libertsinzo7@gmail.com')->firstOrFail();
        Setting::set('api_url', 'http://10.0.0.5:8000/api');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/settings/api-url')
            ->assertOk()
            ->assertJsonPath('api_url', 'http://10.0.0.5:8000/api');

        $staff = User::factory()->create();
        $staff->assignRole(RoleName::STAFF->value);

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/settings/api-url')
            ->assertForbidden();
    }

    public function test_la_page_filament_est_reservee_au_super_administrateur(): void
    {
        $this->seed();

        $admin = User::where('email', 'libertsinzo7@gmail.com')->firstOrFail();
        $this->actingAs($admin);
        $this->assertTrue(ApiSettings::canAccess());

        $staff = User::factory()->create();
        $staff->assignRole(RoleName::STAFF->value);
        $this->actingAs($staff);
        $this->assertFalse(ApiSettings::canAccess());
    }
}
