<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Resources\Donations\DonationResource;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonationGlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_search_finds_a_donation_by_partner_name(): void
    {
        $this->seed();

        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
        $this->actingAs($admin);

        $church = Church::firstOrFail();
        $category = CategoryDon::firstOrFail();

        $partner = Partner::create([
            'first_name' => 'Nestor',
            'last_name' => 'Kouassi',
            'church_id' => $church->id,
        ]);

        $donation = Donation::create([
            'amount' => 15000,
            'date' => '2026-05-12',
            'partner_id' => $partner->id,
            'church_id' => $church->id,
            'category_id' => $category->id,
        ]);

        $results = DonationResource::getGlobalSearchResults('nestor');

        $this->assertCount(1, $results);
        $this->assertStringContainsString('Nestor Kouassi', (string) $results->first()->title);
        $this->assertNotEmpty($donation->fresh());
    }
}
