<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\PeriodType;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Services\PartnershipReportService;
use App\Services\ReportCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_is_reused_until_the_cache_version_changes(): void
    {
        [$church, $category, $partner] = $this->makeData();
        Donation::create([
            'amount' => 100,
            'date' => '2026-03-10',
            'partner_id' => $partner->id,
            'church_id' => $church->id,
            'category_id' => $category->id,
        ]);

        $service = app(PartnershipReportService::class);
        $first = $service->buildReport($church->id, PeriodType::YEAR, 2026, null);

        $this->assertSame(100.0, $first['totals']['amount']);

        // Écriture sans événement modèle : le cache doit encore servir la version précédente.
        Donation::withoutEvents(fn () => Donation::create([
            'amount' => 50,
            'date' => '2026-03-11',
            'partner_id' => $partner->id,
            'church_id' => $church->id,
            'category_id' => $category->id,
        ]));

        $cached = $service->buildReport($church->id, PeriodType::YEAR, 2026, null);
        $this->assertSame(100.0, $cached['totals']['amount']);

        app(ReportCache::class)->invalidate();

        $fresh = $service->buildReport($church->id, PeriodType::YEAR, 2026, null);
        $this->assertSame(150.0, $fresh['totals']['amount']);
    }

    public function test_adding_a_donation_refreshes_the_cached_report(): void
    {
        [$church, $category, $partner] = $this->makeData();
        Donation::create([
            'amount' => 100,
            'date' => '2026-03-10',
            'partner_id' => $partner->id,
            'church_id' => $church->id,
            'category_id' => $category->id,
        ]);

        $service = app(PartnershipReportService::class);
        $first = $service->buildReport($church->id, PeriodType::YEAR, 2026, null);

        Donation::create([
            'amount' => 50,
            'date' => '2026-03-11',
            'partner_id' => $partner->id,
            'church_id' => $church->id,
            'category_id' => $category->id,
        ]);

        $second = $service->buildReport($church->id, PeriodType::YEAR, 2026, null);

        $this->assertSame(100.0, $first['totals']['amount']);
        $this->assertSame(150.0, $second['totals']['amount']);
    }

    /**
     * @return array{0: Church, 1: CategoryDon, 2: Partner}
     */
    private function makeData(): array
    {
        $church = Church::create(['name' => 'CE TEST', 'type' => ChurchType::LOCAL]);
        $category = CategoryDon::create(['name' => 'Dîme', 'color' => '#1F4E78']);
        $partner = Partner::create([
            'first_name' => 'Jean',
            'last_name' => 'Doe',
            'church_id' => $church->id,
        ]);

        return [$church, $category, $partner];
    }
}
