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

class TargetHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_the_exact_target_structure_is_visible_and_manageable_by_the_group_pastor(): void
    {
        // Bénin Groupe 1 (GROUP) — racine
        $root = Church::create(['name' => 'Bénin Groupe 1', 'type' => ChurchType::GROUP]);

        // Sous-groupe 1 et Sous-groupe 2 (SUB_GROUP) sous le groupe
        $sg1 = Church::create(['name' => 'Sous-groupe 1', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $root->id]);
        $sg2 = Church::create(['name' => 'Sous-groupe 2', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $root->id]);

        // CE SIKECODJI = église mère (LOCAL) du Sous-groupe 1, avec ses filles
        $mother = Church::create(['name' => 'CE SIKECODJI MERE', 'type' => ChurchType::LOCAL, 'parent_id' => $sg1->id]);
        $daughter = Church::create(['name' => 'Fille de Sikècodji', 'type' => ChurchType::LOCAL, 'parent_id' => $mother->id]);

        // CE CALAVI = église mère (LOCAL) du Sous-groupe 2, avec ses filles
        $calavi = Church::create(['name' => 'CE CALAVI', 'type' => ChurchType::LOCAL, 'parent_id' => $sg2->id]);
        $calaviDaughter = Church::create(['name' => 'Fille de Calavi', 'type' => ChurchType::LOCAL, 'parent_id' => $calavi->id]);

        // Pasteur Nicolas assigné au GROUPE racine
        $pastor = User::create([
            'name' => 'Pasteur Nicolas',
            'email' => 'nicolas-target@example.test',
            'password' => Hash::make('password'),
            'assigned_church_id' => $root->id,
            'is_active' => true,
        ]);
        $pastor->syncRoles([RoleName::GROUP_PASTOR->value]);

        $access = app(ChurchAccessService::class);

        // Le pasteur de groupe voit TOUTE la profondeur
        foreach ([$sg1, $sg2, $mother, $daughter, $calavi, $calaviDaughter] as $church) {
            $this->assertTrue($access->canAccessChurchData($pastor, $church->id), 'accès refusé à '.$church->name);
            $this->assertTrue($access->canModifyChurchData($pastor, $church->id), 'modification refusée sur '.$church->name);
        }

        // Le sous-groupe ne voit QUE son sous-groupe et ses filles (pas l'autre)
        $sgPastor = User::create([
            'name' => 'Pasteur SG2',
            'email' => 'sg2-target@example.test',
            'password' => Hash::make('password'),
            'assigned_church_id' => $sg2->id,
            'is_active' => true,
        ]);
        $sgPastor->syncRoles([RoleName::SUBGROUP_PASTOR->value]);

        $this->assertTrue($access->canAccessChurchData($sgPastor, $calaviDaughter->id));
        $this->assertFalse($access->canAccessChurchData($sgPastor, $daughter->id));
    }
}
