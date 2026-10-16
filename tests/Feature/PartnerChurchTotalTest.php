<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Filament\Resources\Churches\Pages\ListChurches;
use App\Filament\Resources\Partners\Pages\ListPartners;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerChurchTotalTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private CategoryDon $category;

    private Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::create(['name' => 'Église A', 'type' => ChurchType::LOCAL]);
        $this->category = CategoryDon::create(['name' => 'Dîme']);

        $this->partner = Partner::create([
            'first_name' => 'Jean',
            'last_name' => 'Test',
            'church_id' => $this->church->id,
        ]);

        $this->donation($this->partner, 1000);
        $this->donation($this->partner, 2000, '2026-05-10');
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

    public function test_it_sums_partner_donations(): void
    {
        $found = Partner::withTotalDonations()->findOrFail($this->partner->id);

        $this->assertEquals(3000, $found->total_donations);
    }

    public function test_it_sums_church_donations(): void
    {
        $found = Church::withTotalDonations()->findOrFail($this->church->id);

        $this->assertEquals(3000, $found->total_donations);
    }

    public function test_it_returns_zero_for_a_partner_without_donations(): void
    {
        $other = Partner::create([
            'first_name' => 'Sans',
            'last_name' => 'Don',
            'church_id' => $this->church->id,
        ]);

        $found = Partner::withTotalDonations()->findOrFail($other->id);

        $this->assertEquals(0, $found->total_donations);
    }

    public function test_the_partners_list_page_renders_the_total_column(): void
    {
        $this->seed();

        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();

        Livewire::actingAs($admin)
            ->test(ListPartners::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$this->partner]);
    }

    public function test_the_churches_list_page_renders_the_total_column(): void
    {
        $this->seed();

        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();

        Livewire::actingAs($admin)
            ->test(ListChurches::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$this->church]);
    }
}
