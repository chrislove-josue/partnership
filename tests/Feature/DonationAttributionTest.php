<?php

namespace Tests\Feature;

use App\Enums\CategoryGroup;
use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Models\User;
use App\Services\DonationAttributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DonationAttributionTest extends TestCase
{
    use RefreshDatabase;

    private Church $group;

    private Church $subGroupA;

    private Church $subGroupB;

    private Church $churchA;

    private Church $churchB;

    private User $staffA;

    private Partner $partnerB;

    private CategoryDon $finances;

    private CategoryDon $ministry;

    private CategoryDon $local;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->group = Church::where('name', 'CE SIKECODJI')->firstOrFail();

        $this->subGroupA = Church::create([
            'name' => 'Sous-groupe A', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $this->group->id,
        ]);
        $this->subGroupB = Church::create([
            'name' => 'Sous-groupe B', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $this->group->id,
        ]);
        $this->churchA = Church::create([
            'name' => 'Église A', 'type' => ChurchType::LOCAL, 'parent_id' => $this->subGroupA->id,
        ]);
        $this->churchB = Church::create([
            'name' => 'Église B', 'type' => ChurchType::LOCAL, 'parent_id' => $this->subGroupB->id,
        ]);

        $this->staffA = User::create([
            'name' => 'Staff A',
            'email' => 'staff-attribution@example.test',
            'password' => Hash::make('password'),
            'assigned_church_id' => $this->churchA->id,
            'is_active' => true,
        ]);
        $this->staffA->syncRoles([RoleName::STAFF->value]);

        $this->partnerB = Partner::create([
            'first_name' => 'Visiteur',
            'last_name' => 'De B',
            'email' => 'visiteur-b@example.test',
            'church_id' => $this->churchB->id,
        ]);

        $this->finances = CategoryDon::create(['name' => 'Dîmes attribution', 'group' => CategoryGroup::CHURCH_FINANCES]);
        $this->ministry = CategoryDon::create(['name' => 'Rhapsodie attribution', 'group' => CategoryGroup::MINISTRY_PARTNERSHIP]);
        $this->local = CategoryDon::create(['name' => 'Projet local attribution', 'group' => CategoryGroup::LOCAL_PARTNERSHIP]);
    }

    public function test_finances_and_local_are_attributed_to_the_worship_church(): void
    {
        $service = app(DonationAttributionService::class);

        $this->assertSame(
            $this->churchA->id,
            $service->churchFor($this->partnerB, $this->finances, $this->churchA)->id,
        );
        $this->assertSame(
            $this->churchA->id,
            $service->churchFor($this->partnerB, $this->local, $this->churchA)->id,
        );
    }

    public function test_ministry_is_attributed_to_the_partners_origin_church(): void
    {
        $attribution = app(DonationAttributionService::class)
            ->churchFor($this->partnerB, $this->ministry, $this->churchA);

        $this->assertSame($this->churchB->id, $attribution->id);
    }

    public function test_api_records_a_ministry_donation_for_a_visiting_partner(): void
    {
        $response = $this->actingAs($this->staffA, 'sanctum')->postJson('/api/donations', [
            'amount' => 10000,
            'date' => '2026-11-24',
            'church_id' => $this->churchA->id,
            'partner_id' => $this->partnerB->id,
            'category_id' => $this->ministry->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('donation.church_id', $this->churchB->id)
            ->assertJsonPath('donation.worship_church_id', $this->churchA->id);

        $this->assertDatabaseHas('donations', [
            'partner_id' => $this->partnerB->id,
            'church_id' => $this->churchB->id,
            'worship_church_id' => $this->churchA->id,
            'category_id' => $this->ministry->id,
        ]);
    }

    public function test_api_records_a_finances_donation_for_a_visiting_partner(): void
    {
        $response = $this->actingAs($this->staffA, 'sanctum')->postJson('/api/donations', [
            'amount' => 5000,
            'date' => '2026-11-24',
            'church_id' => $this->churchA->id,
            'partner_id' => $this->partnerB->id,
            'category_id' => $this->finances->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('donation.church_id', $this->churchA->id)
            ->assertJsonPath('donation.worship_church_id', $this->churchA->id);
    }

    public function test_all_partners_are_visible_from_any_church(): void
    {
        $response = $this->actingAs($this->staffA, 'sanctum')->getJson('/api/partners?search=Visiteur');

        $response->assertOk();

        $this->assertTrue(collect($response->json('data'))
            ->contains('id', $this->partnerB->id));
    }

    public function test_a_donation_attributed_elsewhere_is_visible_in_the_worship_church(): void
    {
        $donation = Donation::create([
            'amount' => 10000,
            'date' => '2026-11-24',
            'partner_id' => $this->partnerB->id,
            'church_id' => $this->churchB->id,
            'worship_church_id' => $this->churchA->id,
            'category_id' => $this->ministry->id,
        ]);

        $response = $this->actingAs($this->staffA, 'sanctum')->getJson('/api/donations');

        $response->assertOk();

        $this->assertTrue(collect($response->json('data'))->contains('id', $donation->id));
    }
}
