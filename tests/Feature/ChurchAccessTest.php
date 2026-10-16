<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Models\Church;
use App\Models\User;
use App\Services\ChurchAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChurchAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, int $churchId): User
    {
        $user = User::create([
            'name' => 'User '.$role,
            'email' => strtolower($role).'@example.test',
            'password' => Hash::make('password'),
            'assigned_church_id' => $churchId,
            'is_active' => true,
        ]);

        $user->syncRoles([$role]);

        return $user;
    }

    public function test_super_admin_accesses_every_church(): void
    {
        $this->seed();

        $group = \App\Models\Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $sub = \App\Models\Church::create(['name' => 'SUB', 'type' => \App\Enums\ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $local = \App\Models\Church::create(['name' => 'LOCAL', 'type' => \App\Enums\ChurchType::LOCAL, 'parent_id' => $sub->id]);

        $admin = $this->makeUser(\App\Enums\RoleName::SUPER_ADMIN->value, $group->id);
        $access = app(\App\Services\ChurchAccessService::class);

        $this->assertTrue($access->canAccessChurchData($admin, $local->id));
        $this->assertTrue($access->canModifyChurchData($admin, $local->id));
    }

    public function test_subgroup_pastor_sees_own_church_and_descendants_only(): void
    {
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $sub = Church::create(['name' => 'SUB', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $local = Church::create(['name' => 'LOCAL', 'type' => ChurchType::LOCAL, 'parent_id' => $sub->id]);
        $otherSub = Church::create(['name' => 'SUB2', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $otherLocal = Church::create(['name' => 'LOCAL2', 'type' => ChurchType::LOCAL, 'parent_id' => $otherSub->id]);

        $pastor = $this->makeUser(RoleName::SUBGROUP_PASTOR->value, $sub->id);
        $access = app(ChurchAccessService::class);

        $ids = $access->accessibleChurchIds($pastor);

        $this->assertContains($sub->id, $ids);
        $this->assertContains($local->id, $ids);
        $this->assertNotContains($otherLocal->id, $ids);

        $this->assertTrue($access->canAccessChurchData($pastor, $local->id));
        $this->assertFalse($access->canAccessChurchData($pastor, $otherLocal->id));
    }

    public function test_local_pastor_is_limited_to_own_church(): void
    {
        $this->seed();

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $sub = Church::create(['name' => 'SUB', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $local = Church::create(['name' => 'LOCAL', 'type' => ChurchType::LOCAL, 'parent_id' => $sub->id]);
        $sibling = Church::create(['name' => 'LOCAL-SIB', 'type' => ChurchType::LOCAL, 'parent_id' => $sub->id]);

        $pastor = $this->makeUser(RoleName::LOCAL_PASTOR->value, $local->id);
        $access = app(ChurchAccessService::class);

        $this->assertEquals([$local->id], $access->accessibleChurchIds($pastor));
        $this->assertFalse($access->canAccessChurchData($pastor, $sibling->id));
    }
}
