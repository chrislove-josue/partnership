<?php

namespace Tests\Feature;

use App\Enums\CategoryGroup;
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
use Illuminate\Support\Facades\Hash;
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

        // Sans paramètre : l'église assignée et toute sa descendance.
        $default = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/top-partners?period=month&month=11&year=2026');
        $default->assertOk()
            ->assertJsonPath('church_id', $this->church->id)
            ->assertJsonPath('count', 2);

        // church_id vide = toutes les églises accessibles.
        $all = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/top-partners?period=month&month=11&year=2026&church_id=');
        $all->assertOk()
            ->assertJsonPath('church_id', null)
            ->assertJsonPath('count', 2);
        $this->assertEquals(5000, $all->json('data.0.amount'));

        // Filtre sur une seule église accessible.
        $otherResponse = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/top-partners?period=month&month=11&year=2026&church_id='.$other->id);
        $otherResponse->assertOk()
            ->assertJsonPath('church_id', $other->id)
            ->assertJsonPath('count', 1);
        $this->assertEquals(5000, $otherResponse->json('data.0.amount'));
    }

    public function test_api_filters_by_category_group_and_category(): void
    {
        $alice = $this->partner('Alice');
        $finances = CategoryDon::create(['name' => 'Dîmes top', 'group' => CategoryGroup::CHURCH_FINANCES]);
        $ministry = CategoryDon::create(['name' => 'Rhapsodie top', 'group' => CategoryGroup::MINISTRY_PARTNERSHIP]);

        Donation::create([
            'amount' => 1000,
            'date' => '2026-11-10',
            'partner_id' => $alice->id,
            'church_id' => $this->church->id,
            'worship_church_id' => $this->church->id,
            'category_id' => $finances->id,
        ]);
        Donation::create([
            'amount' => 7000,
            'date' => '2026-11-11',
            'partner_id' => $alice->id,
            'church_id' => $this->church->id,
            'worship_church_id' => $this->church->id,
            'category_id' => $ministry->id,
        ]);

        $base = '?period=month&month=11&year=2026&church_id='.$this->church->id;

        $all = $this->actingAs($this->admin, 'sanctum')->getJson('/api/top-partners'.$base);
        $this->assertEquals(8000, $all->json('data.0.amount'));

        $financesOnly = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/top-partners'.$base.'&category_group=church_finances');
        $financesOnly->assertOk()
            ->assertJsonPath('category_group', 'church_finances');
        $this->assertEquals(1000, $financesOnly->json('data.0.amount'));

        $oneCategory = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/top-partners'.$base.'&category_id='.$ministry->id);
        $oneCategory->assertOk();
        $this->assertEquals(7000, $oneCategory->json('data.0.amount'));
    }

    public function test_ranking_shows_the_credited_church_not_the_partners_origin(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $other = Church::create([
            'name' => 'Église d origine',
            'type' => ChurchType::LOCAL,
            'parent_id' => $group->id,
        ]);

        $visitor = Partner::create([
            'first_name' => 'Visiteur',
            'last_name' => 'Top',
            'email' => 'visiteur-origine-top@example.test',
            'church_id' => $other->id,
        ]);
        $this->donation($visitor, 5000, '2026-11-10'); // crédité à $this->church

        $items = app(TopPartnerService::class)->rank([$this->church->id], PeriodType::MONTH, 2026, 11);

        $this->assertCount(1, $items);
        $this->assertSame($this->church->name, $items->first()['church']);
        $this->assertSame($other->name, $items->first()['origin_church']);
    }

    public function test_subgroup_pastor_only_sees_his_accessible_churches(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $subGroup = Church::create([
            'name' => 'Sous-groupe périmètre',
            'type' => ChurchType::SUB_GROUP,
            'parent_id' => $group->id,
        ]);
        $child = Church::create([
            'name' => 'Église fille périmètre',
            'type' => ChurchType::LOCAL,
            'parent_id' => $subGroup->id,
        ]);
        $otherSubGroup = Church::create([
            'name' => 'Autre sous-groupe périmètre',
            'type' => ChurchType::SUB_GROUP,
            'parent_id' => $group->id,
        ]);
        $outside = Church::create([
            'name' => 'Église hors périmètre',
            'type' => ChurchType::LOCAL,
            'parent_id' => $otherSubGroup->id,
        ]);

        $pastor = User::create([
            'name' => 'Pasteur sous-groupe',
            'email' => 'pasteur-sg-perimetre@example.test',
            'password' => Hash::make('password'),
            'assigned_church_id' => $subGroup->id,
            'is_active' => true,
        ]);
        $pastor->syncRoles([RoleName::SUBGROUP_PASTOR->value]);

        $partner = $this->partner('Alice');
        foreach ([$child->id, $outside->id] as $churchId) {
            Donation::create([
                'amount' => 3000,
                'date' => '2026-11-10',
                'partner_id' => $partner->id,
                'church_id' => $churchId,
                'worship_church_id' => $churchId,
                'category_id' => $this->category->id,
            ]);
        }

        $response = $this->actingAs($pastor, 'sanctum')
            ->getJson('/api/top-partners?period=month&month=11&year=2026&church_id=');

        $response->assertOk();
        // Seul le don de l'église fille est compté (3000 au lieu de 6000).
        $this->assertEquals(3000, $response->json('data.0.amount'));
    }

    public function test_all_option_returns_partners_from_every_church(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $outsideGroup = Church::create(['name' => 'Autre groupe top', 'type' => ChurchType::GROUP]);
        $other = Church::create([
            'name' => 'Église hors périmètre top',
            'type' => ChurchType::LOCAL,
            'parent_id' => $outsideGroup->id,
        ]);

        $pastor = User::create([
            'name' => 'Pasteur groupe périmètre top',
            'email' => 'pasteur-perimetre-top@example.test',
            'password' => Hash::make('password'),
            'assigned_church_id' => $this->church->id,
            'is_active' => true,
        ]);
        $pastor->syncRoles([RoleName::GROUP_PASTOR->value]);

        $outsider = Partner::create([
            'first_name' => 'Hors',
            'last_name' => 'Périmètre',
            'email' => 'hors-perimetre-top@example.test',
            'church_id' => $other->id,
        ]);
        Donation::create([
            'amount' => 9000,
            'date' => '2026-11-10',
            'partner_id' => $outsider->id,
            'church_id' => $other->id,
            'worship_church_id' => $other->id,
            'category_id' => $this->category->id,
        ]);

        $scoped = $this->actingAs($pastor, 'sanctum')
            ->getJson('/api/top-partners?period=month&month=11&year=2026');
        $scoped->assertOk();
        $this->assertFalse(collect($scoped->json('data'))->contains('id', $outsider->id));

        $all = $this->actingAs($pastor, 'sanctum')
            ->getJson('/api/top-partners?period=month&month=11&year=2026&church_id=all');
        $all->assertOk()
            ->assertJsonPath('church_id', null);
        $this->assertTrue(collect($all->json('data'))->contains('id', $outsider->id));
    }

    public function test_non_group_pastors_cannot_see_all_churches(): void
    {
        $pastor = User::create([
            'name' => 'Pasteur sous-groupe droits',
            'email' => 'pasteur-sg-droits@example.test',
            'password' => Hash::make('password'),
            'assigned_church_id' => $this->church->id,
            'is_active' => true,
        ]);
        $pastor->syncRoles([RoleName::SUBGROUP_PASTOR->value]);

        $this->actingAs($pastor, 'sanctum')
            ->getJson('/api/top-partners?period=month&month=11&year=2026&church_id=all')
            ->assertForbidden();

        $staff = User::create([
            'name' => 'Staff droits',
            'email' => 'staff-droits@example.test',
            'password' => Hash::make('password'),
            'assigned_church_id' => $this->church->id,
            'is_active' => true,
        ]);
        $staff->syncRoles([RoleName::STAFF->value]);

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/top-partners?period=month&month=11&year=2026&church_id=all')
            ->assertForbidden();
    }

    public function test_super_admin_can_see_all_churches(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/top-partners?period=month&month=11&year=2026&church_id=all')
            ->assertOk()
            ->assertJsonPath('can_see_all', true);
    }

    public function test_api_rejects_an_invalid_period(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/top-partners?period=invalid')
            ->assertUnprocessable();
    }
}
