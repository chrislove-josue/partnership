<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Models\Church;
use App\Models\User;
use App\Services\ChurchHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChurchHierarchyTest extends TestCase
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

    public function test_deep_hierarchy_is_fully_accessible(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $sub1 = Church::create(['name' => 'Sous-groupe 1', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $localMother = Church::create(['name' => 'CE SIKECODJI locale', 'type' => ChurchType::LOCAL, 'parent_id' => $sub1->id]);
        $deep = Church::create(['name' => 'Église profonde', 'type' => ChurchType::LOCAL, 'parent_id' => $localMother->id]);

        $pastor = $this->makeUser('deep-group@example.test', $group->id, RoleName::GROUP_PASTOR);
        $access = app(ChurchHierarchyService::class);

        $this->assertTrue($access->accessibleChurches($pastor)->contains('id', $deep->id));
        $this->assertTrue(app(\App\Services\ChurchAccessService::class)->canAccessChurchData($pastor, $deep->id));
    }

    public function test_parent_types_and_cycles_are_validated(): void
    {
        $hierarchy = app(ChurchHierarchyService::class);
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $sub = Church::create(['name' => 'SG test', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $local = Church::create(['name' => 'Local test', 'type' => ChurchType::LOCAL, 'parent_id' => $sub->id]);

        $this->assertFalse($hierarchy->isAllowedParent(ChurchType::GROUP, $group));
        $this->assertTrue($hierarchy->isAllowedParent(ChurchType::SUB_GROUP, $group));
        $this->assertTrue($hierarchy->isAllowedParent(ChurchType::LOCAL, $sub));
        $this->assertFalse($hierarchy->isAllowedParent(ChurchType::LOCAL, $local));
        $this->assertTrue($hierarchy->wouldCreateCycle($sub, $sub->id));
        $this->assertTrue($hierarchy->wouldCreateCycle($group, $local->id));
    }

    public function test_api_rejects_an_invalid_parent(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $local = Church::create(['name' => 'Local invalide', 'type' => ChurchType::LOCAL, 'parent_id' => $group->id]);
        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/churches', [
                'name' => 'Nouveau sous-groupe invalide',
                'type' => 'SUB_GROUP',
                'parent_id' => $local->id,
            ])
            ->assertStatus(422);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/churches', [
                'name' => 'Nouveau groupe avec parent',
                'type' => 'GROUP',
                'parent_id' => $group->id,
            ])
            ->assertStatus(422);
    }
}
