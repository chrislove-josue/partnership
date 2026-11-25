<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Models\SyncDeletion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function superAdmin(): User
    {
        return User::where('email', 'sounesatchougo@gmail.com')->firstOrFail();
    }

    public function test_sync_deletions_can_be_paged_with_after_id(): void
    {
        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $admin = $this->superAdmin();

        foreach (['A', 'B', 'C'] as $suffix) {
            Partner::query()->create([
                'first_name' => 'Suppression',
                'last_name' => $suffix,
                'church_id' => $church->id,
            ])->delete();
        }

        $ids = SyncDeletion::query()->orderBy('id')->pluck('id')->all();
        $this->assertCount(3, $ids);

        $first = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/sync/deletions?limit=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('has_more', true);

        $this->assertSame($ids[0], $first->json('data.0.id'));
        $this->assertSame($ids[1], $first->json('data.1.id'));

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/sync/deletions?limit=2&after_id='.$ids[1])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('data.0.id', $ids[2]);
    }

    public function test_root_church_deletion_is_visible_to_super_admin(): void
    {
        $church = Church::query()->create([
            'name' => 'EGLISE RACINE SYNC',
            'type' => ChurchType::GROUP,
        ]);
        $churchId = $church->id;
        $church->delete();

        $this->assertDatabaseHas('sync_deletions', [
            'entity' => 'churches',
            'entity_id' => $churchId,
            'church_id' => null,
        ]);

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->getJson('/api/sync/deletions')
            ->assertOk()
            ->assertJsonFragment([
                'entity' => 'churches',
                'entity_id' => (string) $churchId,
            ]);
    }

    public function test_church_list_exposes_donation_totals(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $local = Church::query()->create([
            'name' => 'EGLISE TOTALE SYNC',
            'type' => ChurchType::LOCAL,
            'parent_id' => $group->id,
        ]);
        $category = CategoryDon::query()->firstOrFail();
        $partner = Partner::query()->create([
            'first_name' => 'Total',
            'last_name' => 'Eglise',
            'church_id' => $group->id,
        ]);

        foreach ([[1500, $group->id], [2500, $local->id]] as [$amount, $churchId]) {
            Donation::query()->create([
                'amount' => $amount,
                'date' => now()->toDateString(),
                'church_id' => $churchId,
                'partner_id' => $partner->id,
                'category_id' => $category->id,
            ]);
        }

        $rows = $this->actingAs($this->superAdmin(), 'sanctum')
            ->getJson('/api/churches?sync=1')
            ->assertOk()
            ->json('data');

        $totals = collect($rows)->mapWithKeys(fn (array $row): array => [
            $row['id'] => $row['total_donations'],
        ]);

        // Un groupe agrège les dons de toute sa descendance (1500 + 2500),
        // une église locale affiche ses dons directs.
        $this->assertEquals(4000, $totals[$group->id]);
        $this->assertEquals(2500, $totals[$local->id]);
    }

    public function test_donations_sync_can_be_paged_with_after_id(): void
    {
        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $category = CategoryDon::query()->firstOrFail();
        $partner = Partner::query()->create([
            'first_name' => 'Sync',
            'last_name' => 'Pagination',
            'church_id' => $church->id,
        ]);

        foreach ([100, 200, 300] as $amount) {
            Donation::query()->create([
                'amount' => $amount,
                'date' => now()->toDateString(),
                'church_id' => $church->id,
                'partner_id' => $partner->id,
                'category_id' => $category->id,
            ]);
        }

        $ids = Donation::query()->orderBy('id')->pluck('id')->all();

        $first = $this->actingAs($this->superAdmin(), 'sanctum')
            ->getJson('/api/donations?sync=1&limit=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('has_more', true);

        $this->assertSame($ids[0], $first->json('data.0.id'));
        $this->assertSame($ids[1], $first->json('data.1.id'));

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->getJson('/api/donations?sync=1&limit=2&after_id='.$ids[1])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('data.0.id', $ids[2]);
    }

    public function test_ui_donation_list_keeps_its_date_filters(): void
    {
        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $category = CategoryDon::query()->firstOrFail();
        $partner = Partner::query()->create([
            'first_name' => 'Filtre',
            'last_name' => 'Date',
            'church_id' => $church->id,
        ]);

        Donation::query()->create([
            'amount' => 100,
            'date' => '2026-01-10',
            'church_id' => $church->id,
            'partner_id' => $partner->id,
            'category_id' => $category->id,
        ]);
        Donation::query()->create([
            'amount' => 200,
            'date' => '2026-02-10',
            'church_id' => $church->id,
            'partner_id' => $partner->id,
            'category_id' => $category->id,
        ]);

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->getJson('/api/donations?date_from=2026-02-01&date_to=2026-02-28')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount', 200);
    }
}
