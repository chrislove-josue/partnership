<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\PeriodType;
use App\Enums\RoleName;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Models\User;
use App\Services\TopPartnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OriginalChurchScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function addDonation(Church $church, float $amount): void
    {
        $partner = Partner::create([
            'first_name' => 'Don',
            'last_name' => uniqid(),
            'email' => uniqid().'@example.test',
            'church_id' => $church->id,
        ]);
        Donation::create([
            'amount' => $amount,
            'date' => now(),
            'partner_id' => $partner->id,
            'church_id' => $church->id,
            'worship_church_id' => $church->id,
            'category_id' => CategoryDon::firstOrFail()->id,
        ]);
    }

    public function test_original_local_church_shows_only_its_direct_donations(): void
    {
        $group = Church::create(['name' => 'Groupe origine', 'type' => ChurchType::GROUP]);
        $sub = Church::create(['name' => 'SG origine', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $original = Church::create(['name' => 'Eglise origine', 'type' => ChurchType::LOCAL, 'parent_id' => $sub->id]);
        $child = Church::create(['name' => 'Fille origine', 'type' => ChurchType::LOCAL, 'parent_id' => $original->id]);

        $this->addDonation($original, 1000);
        $this->addDonation($child, 2000);

        // Église d'origine sélectionnée = ses dons directs uniquement.
        $items = app(TopPartnerService::class)->rank([$original->id], period: PeriodType::MONTH, year: (int) now()->year, index: (int) now()->month);
        $this->assertEquals(1000, $items->sum('amount'));

        // Sous-groupe sélectionné = somme de sa descendance.
        $items = app(TopPartnerService::class)->rank([$sub->id], period: PeriodType::MONTH, year: (int) now()->year, index: (int) now()->month);
        $this->assertEquals(3000, $items->sum('amount'));

        // Groupe sélectionné = somme de sa descendance.
        $items = app(TopPartnerService::class)->rank([$group->id], period: PeriodType::MONTH, year: (int) now()->year, index: (int) now()->month);
        $this->assertEquals(3000, $items->sum('amount'));
    }

    public function test_api_filters_respect_the_same_rule(): void
    {
        $group = Church::create(['name' => 'Groupe api origine', 'type' => ChurchType::GROUP]);
        $original = Church::create(['name' => 'Locale api origine', 'type' => ChurchType::LOCAL, 'parent_id' => $group->id]);
        $child = Church::create(['name' => 'Fille api origine', 'type' => ChurchType::LOCAL, 'parent_id' => $original->id]);

        $this->addDonation($original, 1000);
        $this->addDonation($child, 2000);

        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();

        $local = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/donations?church_id='.$original->id)
            ->assertOk()->json('data');
        $this->assertCount(1, $local);

        $groupRows = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/donations?church_id='.$group->id)
            ->assertOk()->json('data');
        $this->assertCount(2, $groupRows);
    }
}
