<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Models\CategoryDon;
use App\Models\Cellule;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Services\DonationTrendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DonationTrendServiceTest extends TestCase
{
    use RefreshDatabase;

    private Church $churchA;

    private Church $churchB;

    private Cellule $celluleAlpha;

    private Partner $member;

    private Partner $leader;

    private Partner $outsider;

    private CategoryDon $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = CategoryDon::create(['name' => 'Dîme']);

        $this->churchA = Church::create(['name' => 'Église A', 'type' => ChurchType::LOCAL]);
        $this->churchB = Church::create(['name' => 'Église B', 'type' => ChurchType::LOCAL]);

        $this->member = Partner::create([
            'first_name' => 'Membre',
            'last_name' => 'Alpha',
            'church_id' => $this->churchA->id,
        ]);
        $this->leader = Partner::create([
            'first_name' => 'Leader',
            'last_name' => 'Alpha',
            'church_id' => $this->churchA->id,
        ]);
        $this->outsider = Partner::create([
            'first_name' => 'Hors',
            'last_name' => 'Cellule',
            'church_id' => $this->churchB->id,
        ]);

        $this->celluleAlpha = Cellule::create([
            'name' => 'Cellule Alpha',
            'church_id' => $this->churchA->id,
            'leader_id' => $this->leader->id,
        ]);

        $this->member->update(['cellule_id' => $this->celluleAlpha->id]);

        $this->donation($this->churchA, $this->member, '2026-04-10', 1000);
        $this->donation($this->churchA, $this->leader, '2026-04-20', 2000);
        $this->donation($this->churchA, $this->member, '2026-05-10', 3000);
        $this->donation($this->churchB, $this->outsider, '2026-05-15', 5000);
    }

    private function donation(Church $church, Partner $partner, string $date, int $amount): Donation
    {
        return Donation::create([
            'amount' => $amount,
            'date' => $date,
            'partner_id' => $partner->id,
            'church_id' => $church->id,
            'category_id' => $this->category->id,
        ]);
    }

    private function monthLabel(string $ym): string
    {
        return Carbon::parse($ym.'-01')->translatedFormat('M Y');
    }

    public function test_it_builds_monthly_variation_by_partner(): void
    {
        $trend = app(DonationTrendService::class)->byPartner([$this->churchA->id, $this->churchB->id], 10);

        $this->assertSame(
            [$this->monthLabel('2026-04'), $this->monthLabel('2026-05')],
            $trend['labels']
        );

        $this->assertSame(
            ['Hors Cellule', 'Membre Alpha', 'Leader Alpha'],
            array_column($trend['series'], 'label')
        );

        $this->assertEquals([0, 5000], $trend['series'][0]['data']);
        $this->assertEquals([1000, 3000], $trend['series'][1]['data']);
        $this->assertEquals([2000, 0], $trend['series'][2]['data']);
    }

    public function test_it_respects_the_partner_limit(): void
    {
        $trend = app(DonationTrendService::class)->byPartner([$this->churchA->id, $this->churchB->id], 2);

        $this->assertCount(2, $trend['series']);
        $this->assertSame(['Hors Cellule', 'Membre Alpha'], array_column($trend['series'], 'label'));
    }

    public function test_it_builds_monthly_variation_by_church(): void
    {
        $trend = app(DonationTrendService::class)->byChurch([$this->churchA->id, $this->churchB->id]);

        $this->assertSame(['Église A', 'Église B'], array_column($trend['series'], 'label'));
        $this->assertEquals([3000, 3000], $trend['series'][0]['data']);
        $this->assertEquals([0, 5000], $trend['series'][1]['data']);
    }

    public function test_it_builds_monthly_variation_by_cellule_including_leader(): void
    {
        $trend = app(DonationTrendService::class)->byCellule([$this->churchA->id], 10);

        $this->assertSame(['Cellule Alpha'], array_column($trend['series'], 'label'));
        $this->assertEquals([3000, 3000], $trend['series'][0]['data']);
    }

    public function test_it_scopes_results_to_the_given_churches(): void
    {
        $trend = app(DonationTrendService::class)->byPartner([$this->churchA->id], 10);

        $this->assertSame(['Membre Alpha', 'Leader Alpha'], array_column($trend['series'], 'label'));
        $this->assertEquals([1000, 3000], $trend['series'][0]['data']);
        $this->assertEquals([2000, 0], $trend['series'][1]['data']);
    }

    public function test_it_returns_empty_series_without_churches(): void
    {
        $service = app(DonationTrendService::class);

        $this->assertSame(['labels' => [], 'series' => []], $service->byPartner([]));
        $this->assertSame(['labels' => [], 'series' => []], $service->byChurch([]));
        $this->assertSame(['labels' => [], 'series' => []], $service->byCellule([]));
    }
}
