<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TopChurchApiTest extends TestCase
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

    public function test_group_pastor_can_read_the_church_ranking(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $child = Church::create(['name' => 'Église top api', 'type' => ChurchType::LOCAL, 'parent_id' => $group->id]);
        $this->addDonation($group, 1000);
        $this->addDonation($child, 5000);

        $pastor = $this->makeUser('gp-top-church-api@example.test', $group->id, RoleName::GROUP_PASTOR);

        $response = $this->actingAs($pastor, 'sanctum')
            ->getJson('/api/top-churches?period=month&month='.now()->month.'&year='.now()->year);

        $response->assertOk()
            ->assertJsonPath('can_see_all', true)
            ->assertJsonPath('count', 2);
        $this->assertSame($child->name, $response->json('data.0.name'));
    }

    public function test_subgroup_pastor_and_staff_are_forbidden(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $this->addDonation($group, 1000);

        foreach ([RoleName::SUBGROUP_PASTOR, RoleName::LOCAL_PASTOR, RoleName::STAFF] as $role) {
            $user = $this->makeUser('top-church-'.strtolower($role->value).'@example.test', $group->id, $role);
            $this->actingAs($user, 'sanctum')
                ->getJson('/api/top-churches')
                ->assertForbidden();
        }
    }

    public function test_super_admin_can_read_the_church_ranking(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $this->addDonation($group, 2000);

        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/top-churches')
            ->assertOk()
            ->assertJsonPath('can_see_all', true);
    }
}
