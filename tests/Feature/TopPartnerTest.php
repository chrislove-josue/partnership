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

class TopPartnerTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $admin;

    private CategoryDon $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $this->admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
        $this->category = CategoryDon::firstOrFail();
    }

    private function partner(string $name): Partner
    {
        return Partner::create([
            'first_name' => $name,
            'last_name' => 'Top',
            'email' => strtolower($name).'-top@example.test',
            'church_id' => $this->church->id,
        ]);
    }

    private function donation(Partner $partner, float $amount, string $date): void
    {
        Donation::create([
            'amount' => $amount,
            'date' => $date,
            'partner_id' => $partner->id,
            'church_id' => $this->church->id,
            'worship_church_id' => $this->church->id,
            'category_id' => $this->category->id,
        ]);
    }

    public function test_rank_aggregates_by_partner_and_sorts_desc(): void
    {
        $alice = $this->partner('Alice');
        $bob = $this->partner('Bob');

        $this->donation($alice, 1000, '2026-11-02');
        $this->donation($alice, 2000, '2026-11-10');
        $this->donation($bob, 500, '2026-11-15');

        $items = app(TopPartnerService::class)->rank([$this->church->id], PeriodType::MONTH, 2026, 11);

        $this->assertCount(2, $items);
        $this->assertSame('Alice Top', $items->first()['name']);
        $this->assertEquals(3000, $items->first()['amount']);
        $this->assertSame(2, $items->first()['count']);
    }

    public function test_rank_respects_month_quarter_and_year(): void
    {
        $alice = $this->partner('Alice');

        $this->donation($alice, 100, '2026-01-10');
        $this->donation($alice, 200, '2026-04-10');
        $this->donation($alice, 300, '2026-11-10');
        $this->donation($alice, 400, '2025-11-10');
        $this->donation($alice, 500, '2026-12-10'); // désactivé ci-dessous
        Donation::query()->where('amount', 500)->update(['is_active' => false]);

        $service = app(TopPartnerService::class);
        $ids = [$this->church->id];

        $this->assertEquals(300, $service->rank($ids, PeriodType::MONTH, 2026, 11)->first()['amount']);
        $this->assertEquals(300, $service->rank($ids, PeriodType::QUARTER, 2026, 4)->first()['amount']);
        $this->assertEquals(600, $service->rank($ids, PeriodType::YEAR, 2026, 1)->first()['amount']);
        $this->assertEquals(400, $service->rank($ids, PeriodType::MONTH, 2025, 11)->first()['amount']);
    }

    public function test_api_returns_ranking_for_the_given_period(): void
    {
        $alice = $this->partner('Alice');
        $this->donation($alice, 1500, '2026-11-10');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/top-partners?period=month&month=11&year=2026');

        $response->assertOk()
            ->assertJsonPath('period', 'month')
            ->assertJsonPath('year', 2026)
            ->assertJsonPath('index', 11)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.amount', 1500);
    }

    public function test_api_filters_by_the_pastors_own_church_then_by_an_accessible_one(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $other = Church::create([
            'name' => 'Autre église top',
            'type' => ChurchType::LOCAL,
            'parent_id' => $group->id,
        ]);

        $alice = $this->partner('Alice');
        $bob = Partner::create([
            'first_name' => 'Bob',
            'last_name' => 'Top',
            'email' => 'bob-autre-top@example.test',
            'church_id' => $other->id,
        ]);

        $this->donation($alice, 1000, '2026-11-10');

        Donation::create([
            'amount' => 5000,
            'date' => '2026-11-10',
            'partner_id' => $bob->id,
            'church_id' => $other->id,
            'worship_church_id' => $other->id,
            'category_id' => $this->category->id,
        ]);

        // Sans filtre explicite : l'église assignée est utilisée par défaut.
        $default = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/top-partners?period=month&month=11&year=2026');
        $default->assertOk();
        $this->assertSame(1, $default->json('count'));

        // Filtre sur une autre église accessible.
        $otherResponse = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/top-partners?period=month&month=11&year=2026&church_id='.$other->id);
        $otherResponse->assertOk()
            ->assertJsonPath('church_id', $other->id)
            ->assertJsonPath('count', 1);
        $this->assertEquals(5000, $otherResponse->json('data.0.amount'));
    }

    public function test_api_rejects_an_invalid_period(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/top-partners?period=invalid')
            ->assertUnprocessable();
    }
}
