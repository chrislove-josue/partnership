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
use App\Services\PartnershipReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DonationDeactivationTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $pastor;

    private Partner $partner;

    private CategoryDon $category;

    private Donation $donation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $this->church = Church::create([
            'name' => 'Église désactivation',
            'type' => ChurchType::LOCAL,
            'parent_id' => $group->id,
        ]);

        $this->pastor = User::create([
            'name' => 'Pasteur désactivation',
            'email' => 'pasteur-desactivation@example.test',
            'password' => Hash::make('password'),
            'assigned_church_id' => $this->church->id,
            'is_active' => true,
        ]);
        $this->pastor->syncRoles([RoleName::LOCAL_PASTOR->value]);

        $this->partner = Partner::create([
            'first_name' => 'Jean',
            'last_name' => 'Désactivation',
            'church_id' => $this->church->id,
        ]);

        $this->category = CategoryDon::firstOrFail();

        $this->donation = Donation::create([
            'amount' => 25000,
            'date' => '2026-11-24',
            'partner_id' => $this->partner->id,
            'church_id' => $this->church->id,
            'worship_church_id' => $this->church->id,
            'category_id' => $this->category->id,
        ]);
    }

    public function test_pastor_can_deactivate_and_reactivate_a_donation(): void
    {
        $this->actingAs($this->pastor, 'sanctum')
            ->postJson("/api/donations/{$this->donation->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('donation.is_active', false);

        $this->assertDatabaseHas('donations', ['id' => $this->donation->id, 'is_active' => false]);

        $this->actingAs($this->pastor, 'sanctum')
            ->postJson("/api/donations/{$this->donation->id}/activate")
            ->assertOk()
            ->assertJsonPath('donation.is_active', true);
    }

    public function test_deactivating_twice_returns_conflict(): void
    {
        $this->donation->update(['is_active' => false]);

        $this->actingAs($this->pastor, 'sanctum')
            ->postJson("/api/donations/{$this->donation->id}/deactivate")
            ->assertStatus(409);
    }

    public function test_inactive_donation_is_excluded_from_the_api_list(): void
    {
        $this->donation->update(['is_active' => false]);

        $response = $this->actingAs($this->pastor, 'sanctum')->getJson('/api/donations');

        $response->assertOk();

        $this->assertFalse(collect($response->json('data'))->contains('id', $this->donation->id));
    }

    public function test_inactive_donation_is_excluded_from_totals_and_reports(): void
    {
        $this->donation->update(['is_active' => false]);

        $found = Church::withTotalDonations()->findOrFail($this->church->id);
        $this->assertEquals(0, (float) $found->total_donations);

        $found = Partner::withTotalDonations()->findOrFail($this->partner->id);
        $this->assertEquals(0, (float) $found->total_donations);

        $report = app(PartnershipReportService::class)->buildReport(
            $this->church->id,
            PeriodType::MONTH,
            2026,
            11,
        );
        $this->assertEquals(0, (float) $report['totals']['amount']);
        $this->assertSame(0, $report['totals']['donationCount']);
    }

    public function test_inactive_donation_can_still_be_reactivated_and_counted(): void
    {
        $this->donation->update(['is_active' => false]);

        $this->actingAs($this->pastor, 'sanctum')
            ->postJson("/api/donations/{$this->donation->id}/activate")
            ->assertOk();

        $found = Church::withTotalDonations()->findOrFail($this->church->id);
        $this->assertEquals(25000, (float) $found->total_donations);
    }

    public function test_status_filter_can_list_and_find_inactive_donations(): void
    {
        $this->donation->update(['is_active' => false]);

        $active = $this->actingAs($this->pastor, 'sanctum')->getJson('/api/donations');
        $active->assertOk();
        $this->assertFalse(collect($active->json('data'))->contains('id', $this->donation->id));

        $inactive = $this->actingAs($this->pastor, 'sanctum')->getJson('/api/donations?status=inactive');
        $inactive->assertOk();
        $this->assertTrue(collect($inactive->json('data'))->contains('id', $this->donation->id));
        // Le champ est indispensable pour que l'APK affiche « Réactiver ».
        $inactive->assertJsonPath('data.0.is_active', false);

        $all = $this->actingAs($this->pastor, 'sanctum')->getJson('/api/donations?status=all');
        $all->assertOk();
        $this->assertTrue(collect($all->json('data'))->contains('id', $this->donation->id));
    }

    public function test_pastor_can_modify_an_inactive_donation_without_reactivating_it(): void
    {
        $this->donation->update(['is_active' => false]);

        $this->actingAs($this->pastor, 'sanctum')
            ->patchJson("/api/donations/{$this->donation->id}", ['amount' => 30000])
            ->assertOk();

        $this->assertDatabaseHas('donations', [
            'id' => $this->donation->id,
            'amount' => 30000,
            'is_active' => false,
        ]);
    }

    public function test_staff_and_pastor_cannot_delete_a_donation(): void
    {
        $this->actingAs($this->pastor, 'sanctum')
            ->deleteJson("/api/donations/{$this->donation->id}")
            ->assertForbidden();

        $staff = User::create([
            'name' => 'Staff suppression',
            'email' => 'staff-suppression@example.test',
            'password' => Hash::make('password'),
            'assigned_church_id' => $this->church->id,
            'is_active' => true,
        ]);
        $staff->syncRoles([RoleName::STAFF->value]);

        $this->actingAs($staff, 'sanctum')
            ->deleteJson("/api/donations/{$this->donation->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('donations', ['id' => $this->donation->id]);
    }

    public function test_pastor_can_modify_and_deactivate_but_not_delete(): void
    {
        $this->actingAs($this->pastor, 'sanctum')
            ->patchJson("/api/donations/{$this->donation->id}", ['amount' => 30000])
            ->assertOk();

        $this->actingAs($this->pastor, 'sanctum')
            ->postJson("/api/donations/{$this->donation->id}/deactivate")
            ->assertOk();

        $this->actingAs($this->pastor, 'sanctum')
            ->deleteJson("/api/donations/{$this->donation->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('donations', [
            'id' => $this->donation->id,
            'amount' => 30000,
            'is_active' => false,
        ]);
    }
}
