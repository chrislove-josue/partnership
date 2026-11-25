<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\PeriodType;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Services\PartnershipReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function addDonation(Church $church, float $amount): Partner
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

        return $partner;
    }

    public function test_report_groups_donations_of_the_whole_subtree(): void
    {
        $root = Church::create(['name' => 'Groupe rapport', 'type' => ChurchType::GROUP]);
        $sg = Church::create(['name' => 'SG rapport', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $root->id]);
        $mother = Church::create(['name' => 'Mère rapport', 'type' => ChurchType::LOCAL, 'parent_id' => $sg->id]);
        $daughter = Church::create(['name' => 'Fille rapport', 'type' => ChurchType::LOCAL, 'parent_id' => $mother->id]);

        $this->addDonation($root, 1000);
        $this->addDonation($sg, 2000);
        $this->addDonation($mother, 3000);
        $daughterPartner = $this->addDonation($daughter, 4000);

        $report = app(PartnershipReportService::class)->buildReport(
            $root->id,
            PeriodType::MONTH,
            (int) now()->year,
            (int) now()->month,
        );

        // 1000 + 2000 + 3000 + 4000 = 10000 (au lieu de 1000 sans regroupement)
        $this->assertEquals(10000, (float) $report['totals']['amount']);
        $this->assertSame(4, $report['totals']['donationCount']);

        $donationChurches = array_column($report['donations'], 'church');
        $this->assertContains('Fille rapport', $donationChurches);
        $this->assertContains('Mère rapport', $donationChurches);

        // Les partenaires des églises filles sont aussi pris en compte
        $partnerIds = array_column($report['partners'], 'id');
        $this->assertContains($daughterPartner->id, $partnerIds);
    }
}
