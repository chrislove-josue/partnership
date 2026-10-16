<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Filament\Resources\Cellules\Pages\ListCellules;
use App\Models\CategoryDon;
use App\Models\Cellule;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CelluleTotalTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private CategoryDon $category;

    private Cellule $celluleA;

    private Partner $member;

    private Partner $leader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::create(['name' => 'Église A', 'type' => ChurchType::LOCAL]);
        $this->category = CategoryDon::create(['name' => 'Dîme']);

        $this->member = Partner::create([
            'first_name' => 'Membre',
            'last_name' => 'A',
            'church_id' => $this->church->id,
        ]);
        $this->leader = Partner::create([
            'first_name' => 'Leader',
            'last_name' => 'A',
            'church_id' => $this->church->id,
        ]);

        $this->celluleA = Cellule::create([
            'name' => 'Cellule A',
            'church_id' => $this->church->id,
            'leader_id' => $this->leader->id,
        ]);

        $this->member->update(['cellule_id' => $this->celluleA->id]);

        $this->donation($this->member, 1000);
        $this->donation($this->leader, 2000);
    }

    private function donation(Partner $partner, int $amount, string $date = '2026-04-10'): Donation
    {
        return Donation::create([
            'amount' => $amount,
            'date' => $date,
            'partner_id' => $partner->id,
            'church_id' => $this->church->id,
            'category_id' => $this->category->id,
        ]);
    }

    public function test_the_scope_sums_donations_from_members_and_leader(): void
    {
        $cellule = Cellule::withTotalDonations()->findOrFail($this->celluleA->id);

        $this->assertEquals(3000, $cellule->total_donations);
    }

    public function test_a_partner_counts_in_each_cellule_they_belong_to(): void
    {
        $celluleB = Cellule::create([
            'name' => 'Cellule B',
            'church_id' => $this->church->id,
            'leader_id' => $this->member->id,
        ]);

        $this->donation($this->member, 500);

        $a = Cellule::withTotalDonations()->findOrFail($this->celluleA->id);
        $b = Cellule::withTotalDonations()->findOrFail($celluleB->id);

        $this->assertEquals(3500, $a->total_donations);
        $this->assertEquals(1500, $b->total_donations);
    }

    public function test_it_excludes_donations_from_other_cellules(): void
    {
        $outsider = Partner::create([
            'first_name' => 'Hors',
            'last_name' => 'Cellule',
            'church_id' => $this->church->id,
        ]);
        $this->donation($outsider, 9999);

        $cellule = Cellule::withTotalDonations()->findOrFail($this->celluleA->id);

        $this->assertEquals(3000, $cellule->total_donations);
    }

    public function test_the_list_page_renders_the_total_column(): void
    {
        $this->seed();

        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();

        Livewire::actingAs($admin)
            ->test(ListCellules::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$this->celluleA]);
    }
}
