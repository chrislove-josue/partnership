<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChurchDeactivationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, ?int $churchId): User
    {
        $user = User::create([
            'name' => 'User '.$role,
            'email' => strtolower($role).'-'.uniqid().'@example.test',
            'password' => Hash::make('password'),
            'assigned_church_id' => $churchId,
            'is_active' => true,
        ]);

        $user->syncRoles([$role]);

        return $user;
    }

    private function hierarchy(): array
    {
        $group = Church::create(['name' => 'Groupe test', 'type' => ChurchType::GROUP]);
        $sub = Church::create([
            'name' => 'Sous-groupe test',
            'type' => ChurchType::SUB_GROUP,
            'parent_id' => $group->id,
        ]);
        $local = Church::create([
            'name' => 'Locale test',
            'type' => ChurchType::LOCAL,
            'parent_id' => $sub->id,
        ]);
        $other = Church::create([
            'name' => 'Autre sous-groupe test',
            'type' => ChurchType::SUB_GROUP,
            'parent_id' => $group->id,
        ]);

        return [$group, $sub, $local, $other];
    }

    public function test_seul_le_super_administrateur_peut_supprimer_une_eglise(): void
    {
        $this->seed();

        [$group, $sub, $local] = $this->hierarchy();
        $pastor = $this->makeUser(RoleName::GROUP_PASTOR->value, $group->id);
        $admin = $this->makeUser(RoleName::SUPER_ADMIN->value, null);

        $this->actingAs($pastor, 'sanctum')
            ->deleteJson("/api/churches/{$local->id}")
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/churches/{$local->id}")
            ->assertOk()
            ->assertJson(['message' => 'Église supprimée.']);

        $this->assertDatabaseMissing('churches', ['id' => $local->id]);
        $this->assertDatabaseHas('churches', ['id' => $sub->id]);
    }

    public function test_le_pasteur_de_groupe_peut_desactiver_et_reactiver_son_eglise_et_ses_descendants(): void
    {
        $this->seed();

        [$group, $sub, $local, $other] = $this->hierarchy();
        $unrelated = Church::create(['name' => 'Groupe étranger test', 'type' => ChurchType::GROUP]);
        $pastor = $this->makeUser(RoleName::GROUP_PASTOR->value, $group->id);

        $this->actingAs($pastor, 'sanctum')
            ->postJson("/api/churches/{$local->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('churches', ['id' => $local->id, 'is_active' => false]);

        $this->actingAs($pastor, 'sanctum')
            ->postJson("/api/churches/{$other->id}/deactivate")
            ->assertOk();

        $this->actingAs($pastor, 'sanctum')
            ->postJson("/api/churches/{$unrelated->id}/deactivate")
            ->assertForbidden();

        $this->actingAs($pastor, 'sanctum')
            ->postJson("/api/churches/{$local->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('churches', ['id' => $local->id, 'is_active' => true]);
    }

    public function test_le_pasteur_de_sous_groupe_peut_desactiver_son_eglise_et_ses_filles(): void
    {
        $this->seed();

        [$group, $sub, $local] = $this->hierarchy();
        $pastor = $this->makeUser(RoleName::SUBGROUP_PASTOR->value, $sub->id);
        $localPastor = $this->makeUser(RoleName::LOCAL_PASTOR->value, $local->id);

        $this->actingAs($pastor, 'sanctum')
            ->postJson("/api/churches/{$sub->id}/deactivate")
            ->assertOk();

        $this->assertDatabaseHas('churches', ['id' => $sub->id, 'is_active' => false]);

        $this->actingAs($localPastor, 'sanctum')
            ->postJson("/api/churches/{$local->id}/deactivate")
            ->assertForbidden();

        $this->actingAs($pastor, 'sanctum')
            ->postJson("/api/churches/{$local->id}/deactivate")
            ->assertOk();
    }

    public function test_le_super_administrateur_peut_desactiver_n_importe_quelle_eglise(): void
    {
        $this->seed();

        [, , $local] = $this->hierarchy();
        $admin = $this->makeUser(RoleName::SUPER_ADMIN->value, null);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/churches/{$local->id}/deactivate")
            ->assertOk();

        $this->assertDatabaseHas('churches', ['id' => $local->id, 'is_active' => false]);
    }

    public function test_la_liste_des_eglises_expose_le_statut_actif(): void
    {
        $this->seed();

        [, , $local] = $this->hierarchy();
        $admin = $this->makeUser(RoleName::SUPER_ADMIN->value, null);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/churches')
            ->assertOk();

        $entry = collect($response->json('data'))->firstWhere('id', $local->id);

        $this->assertNotNull($entry);
        $this->assertTrue($entry['is_active']);
    }
}
