<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Resources\Donations\Pages\ListDonations;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DonationsTableFilterTest extends TestCase
{
    use RefreshDatabase;

    private function donation(Church $church, Partner $partner, CategoryDon $category, string $date, int $amount): Donation
    {
        return Donation::create([
            'amount' => $amount,
            'date' => $date,
            'partner_id' => $partner->id,
            'church_id' => $church->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_it_filters_donations_by_date_range_and_partner(): void
    {
        $this->seed();

        $superAdmin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $category = CategoryDon::firstOrFail();

        $partnerA = Partner::create(['first_name' => 'Alice', 'last_name' => 'A', 'church_id' => $church->id]);
        $partnerB = Partner::create(['first_name' => 'Bruno', 'last_name' => 'B', 'church_id' => $church->id]);

        $avrilA = $this->donation($church, $partnerA, $category, '2026-04-15', 10000);
        $aoutA = $this->donation($church, $partnerA, $category, '2026-08-15', 20000);
        $avrilB = $this->donation($church, $partnerB, $category, '2026-04-20', 30000);

        // Filtre "Période" : uniquement avril 2026.
        Livewire::actingAs($superAdmin)
            ->test(ListDonations::class)
            ->filterTable('periode', [
                'date_from' => '2026-04-01',
                'date_to' => '2026-04-30',
            ])
            ->assertCanSeeTableRecords([$avrilA, $avrilB])
            ->assertCanNotSeeTableRecords([$aoutA]);

        // Filtre "Partenaire" : uniquement Alice (repart de zéro car les filtres se cumulent).
        Livewire::actingAs($superAdmin)
            ->test(ListDonations::class)
            ->filterTable('partner_id', $partnerA->id)
            ->assertCanSeeTableRecords([$avrilA, $aoutA])
            ->assertCanNotSeeTableRecords([$avrilB]);
    }
}
