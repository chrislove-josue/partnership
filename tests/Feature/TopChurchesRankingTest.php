<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Filament\Pages\TopPartners;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class TopChurchesRankingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function makeUser(string $email, int $churchId, RoleName $role): User
    {
        $user = User::create([
            'name' => 'Test',
            'email' => $email,
            'password' => Hash::make('password'),
            'assigned_church_id' => $churchId,
            'is_active' => true,
        ]);
        $user->syncRoles([$role->value]);

        return $user;
    }

    private function addDonation(Church $church, float $amount): void
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
    }

    public function test_group_pastor_sees_the_church_ranking(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $child = Church::create(['name' => 'Église top test', 'type' => ChurchType::LOCAL, 'parent_id' => $group->id]);
        $this->addDonation($group, 1000);
        $this->addDonation($child, 5000);

        $pastor = $this->makeUser('gp-top-churches@example.test', $group->id, RoleName::GROUP_PASTOR);
        $component = Livewire::actingAs($pastor)->test(TopPartners::class)->assertOk();

        $items = $component->instance()->getTopChurches()['items'];

        $this->assertCount(2, $items);
        $this->assertSame($child->name, $items->first()['name']);
        $this->assertEquals(5000, $items->first()['amount']);
    }

    public function test_subgroup_pastor_and_staff_do_not_see_the_church_ranking(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $this->addDonation($group, 1000);

        $subgroupPastor = $this->makeUser('sgp-top-churches@example.test', $group->id, RoleName::SUBGROUP_PASTOR);
        $this->assertSame(
            [],
            Livewire::actingAs($subgroupPastor)->test(TopPartners::class)->assertOk()
                ->instance()->getTopChurches(),
        );

        $staff = $this->makeUser('staff-top-churches@example.test', $group->id, RoleName::STAFF);
        $this->assertSame(
            [],
            Livewire::actingAs($staff)->test(TopPartners::class)->assertOk()
                ->instance()->getTopChurches(),
        );
    }

    public function test_super_admin_sees_the_church_ranking(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $this->addDonation($group, 2000);

        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
        $items = Livewire::actingAs($admin)->test(TopPartners::class)->assertOk()
            ->instance()->getTopChurches()['items'];

        $this->assertNotEmpty($items);
    }
}
