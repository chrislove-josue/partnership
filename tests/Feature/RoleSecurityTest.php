<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\Church;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class RoleSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
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

    public function test_group_pastor_cannot_edit_the_super_admin_role(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $pastor = $this->makeUser(RoleName::GROUP_PASTOR->value, $group, 'pasteur-role@example.test');
        $superAdminRole = Role::where('name', RoleName::SUPER_ADMIN->value)->firstOrFail();

        $this->actingAs($pastor)
            ->get(RoleResource::getUrl('edit', ['record' => $superAdminRole]))
            ->assertForbidden();

        $this->assertDatabaseHas('roles', [
            'id' => $superAdminRole->id,
            'name' => RoleName::SUPER_ADMIN->value,
            'is_system' => true,
        ]);
    }

    public function test_group_pastor_cannot_create_or_delete_roles(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $pastor = $this->makeUser(RoleName::GROUP_PASTOR->value, $group, 'pasteur-roles@example.test');
        $staffRole = Role::where('name', RoleName::STAFF->value)->firstOrFail();

        $this->assertTrue(Gate::forUser($pastor)->allows('viewAny', Role::class));
        $this->assertTrue(Gate::forUser($pastor)->denies('create', Role::class));
        $this->assertTrue(Gate::forUser($pastor)->denies('update', $staffRole));
        $this->assertTrue(Gate::forUser($pastor)->denies('delete', $staffRole));
    }

    public function test_group_pastor_form_cannot_change_sensitive_role_fields(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $pastor = $this->makeUser(RoleName::GROUP_PASTOR->value, $group, 'pasteur-form-role@example.test');
        $role = Role::where('name', RoleName::STAFF->value)->firstOrFail();

        Livewire::actingAs($pastor)
            ->test(EditRole::class, ['record' => $role->id])
            ->fillForm([
                'name' => 'ROLE ESCALADE',
                'is_system' => true,
            ])
            ->assertFormSet([
                'name' => $role->name,
                'is_system' => $role->is_system,
            ]);
    }

    public function test_super_admin_keeps_role_management(): void
    {
        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
        $customRole = Role::create(['name' => 'ROLE PERSONNALISE', 'guard_name' => 'web', 'is_system' => false]);
        $superAdminRole = Role::where('name', RoleName::SUPER_ADMIN->value)->firstOrFail();

        $this->assertTrue(Gate::forUser($admin)->allows('create', Role::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $superAdminRole));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $customRole));
        $this->assertTrue(Gate::forUser($admin)->denies('delete', $superAdminRole));
    }
}
