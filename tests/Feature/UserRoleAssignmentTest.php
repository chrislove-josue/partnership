<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Church;
use App\Models\Role;
use App\Models\User;
use App\Services\ChurchAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class UserRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Church $group;
    private Church $sub;
    private Church $local;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $this->sub = Church::create(['name' => 'CE CALAVI', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $this->group->id]);
        $this->local = Church::create(['name' => 'CE PARAKOU', 'type' => ChurchType::LOCAL, 'parent_id' => $this->sub->id]);
    }

    private function makeUser(string $role, Church $church, string $email): User
    {
        $user = User::create([
            'name' => 'Compte '.$role,
            'email' => $email,
            'password' => Hash::make('azerty123'),
            'assigned_church_id' => $church->id,
            'is_active' => true,
        ]);
        $user->syncRoles([$role]);

        return $user;
    }

    /** @return array<string, mixed> */
    private function payload(string $role, ?Church $church = null): array
    {
        return [
            'name' => 'Nouveau personnel',
            'email' => 'nouveau-'.uniqid().'@example.test',
            'password' => 'azerty123',
            'roles' => [$role],
            'assigned_church_id' => ($church ?? $this->local)->id,
        ];
    }

    public function test_manageable_roles_follow_the_hierarchy(): void
    {
        $access = app(ChurchAccessService::class);

        $local = $this->makeUser(RoleName::LOCAL_PASTOR->value, $this->local, 'lp@example.test');
        $sub = $this->makeUser(RoleName::SUBGROUP_PASTOR->value, $this->sub, 'sp@example.test');
        $group = $this->makeUser(RoleName::GROUP_PASTOR->value, $this->group, 'gp@example.test');
        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();

        $this->assertTrue($access->canManageUser($local, RoleName::STAFF->value));
        $this->assertFalse($access->canManageUser($local, RoleName::LOCAL_PASTOR->value));

        $this->assertTrue($access->canManageUser($sub, RoleName::LOCAL_PASTOR->value));
        $this->assertTrue($access->canManageUser($sub, RoleName::STAFF->value));
        $this->assertFalse($access->canManageUser($sub, RoleName::SUBGROUP_PASTOR->value));

        $this->assertTrue($access->canManageUser($group, RoleName::SUBGROUP_PASTOR->value));
        $this->assertTrue($access->canManageUser($group, RoleName::LOCAL_PASTOR->value));
        $this->assertTrue($access->canManageUser($group, RoleName::STAFF->value));
        $this->assertFalse($access->canManageUser($group, RoleName::GROUP_PASTOR->value));

        foreach (RoleName::cases() as $role) {
            $this->assertTrue($access->canManageUser($admin, $role->value));
        }
    }

    public function test_local_pastor_can_only_create_staff_through_the_api(): void
    {
        $pastor = $this->makeUser(RoleName::LOCAL_PASTOR->value, $this->local, 'lp@example.test');

        $this->actingAs($pastor, 'sanctum')
            ->postJson('/api/users', $this->payload(RoleName::LOCAL_PASTOR->value))
            ->assertForbidden();

        $this->actingAs($pastor, 'sanctum')
            ->postJson('/api/users', $this->payload(RoleName::STAFF->value))
            ->assertCreated();
    }

    public function test_subgroup_pastor_can_create_local_pastors_and_staff_only(): void
    {
        $pastor = $this->makeUser(RoleName::SUBGROUP_PASTOR->value, $this->sub, 'sp@example.test');

        $this->actingAs($pastor, 'sanctum')
            ->postJson('/api/users', $this->payload(RoleName::SUBGROUP_PASTOR->value, $this->sub))
            ->assertForbidden();

        $this->actingAs($pastor, 'sanctum')
            ->postJson('/api/users', $this->payload(RoleName::LOCAL_PASTOR->value))
            ->assertCreated();

        $this->actingAs($pastor, 'sanctum')
            ->postJson('/api/users', $this->payload(RoleName::STAFF->value, $this->sub))
            ->assertCreated();
    }

    public function test_group_pastor_can_create_subgroups_locals_and_staff_but_not_group_pastors(): void
    {
        $pastor = $this->makeUser(RoleName::GROUP_PASTOR->value, $this->group, 'gp@example.test');

        $this->actingAs($pastor, 'sanctum')
            ->postJson('/api/users', $this->payload(RoleName::GROUP_PASTOR->value, $this->group))
            ->assertForbidden();

        foreach ([RoleName::SUBGROUP_PASTOR, RoleName::LOCAL_PASTOR, RoleName::STAFF] as $role) {
            $church = $role === RoleName::SUBGROUP_PASTOR ? $this->sub : $this->local;

            $this->actingAs($pastor, 'sanctum')
                ->postJson('/api/users', $this->payload($role->value, $church))
                ->assertCreated();
        }
    }

    public function test_super_admin_can_assign_every_role(): void
    {
        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', $this->payload(RoleName::GROUP_PASTOR->value, $this->group))
            ->assertCreated();
    }

    public function test_filament_create_page_rejects_an_injected_unauthorized_role(): void
    {
        Mail::fake();
        $pastor = $this->makeUser(RoleName::LOCAL_PASTOR->value, $this->local, 'lp@example.test');
        $staffId = Role::where('name', RoleName::STAFF->value)->firstOrFail()->id;
        $localPastorId = Role::where('name', RoleName::LOCAL_PASTOR->value)->firstOrFail()->id;

        Livewire::actingAs($pastor)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Personnel_test',
                'email' => 'personnel-test@example.test',
                'password' => 'azerty123',
                'roles' => [RoleName::LOCAL_PASTOR->value],
                'assigned_church_id' => $this->local->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasErrors(['data.roles.0']);

        $this->assertDatabaseMissing('users', ['email' => 'personnel-test@example.test']);

        Livewire::actingAs($pastor)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Personnel_test',
                'email' => 'personnel-test@example.test',
                'password' => 'azerty123',
                'roles' => [RoleName::STAFF->value],
                'assigned_church_id' => $this->local->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'personnel-test@example.test']);
    }
}
