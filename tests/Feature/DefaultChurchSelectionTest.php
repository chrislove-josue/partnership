<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Filament\Pages\PartnershipReport;
use App\Filament\Resources\Cellules\Pages\CreateCellule;
use App\Filament\Resources\Churches\Pages\CreateChurch;
use App\Filament\Resources\Donations\Pages\CreateDonation;
use App\Filament\Resources\Partners\Pages\CreatePartner;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'église du compte doit être proposée par défaut dans les formulaires, sans
 * obliger l'utilisateur à la rechercher parmi toutes les églises accessibles.
 */
class DefaultChurchSelectionTest extends TestCase
{
    use RefreshDatabase;

    private Church $group;

    private Church $local;

    private User $localPastor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $this->local = Church::create([
            'name' => 'CE LOCAL TEST',
            'type' => ChurchType::LOCAL,
            'parent_id' => $this->group->id,
        ]);

        $this->localPastor = User::create([
            'name' => 'Pasteur Local',
            'email' => 'pasteur.local@example.test',
            'password' => Hash::make('azerty123'),
            'assigned_church_id' => $this->local->id,
            'is_active' => true,
        ]);
        $this->localPastor->syncRoles([RoleName::LOCAL_PASTOR->value]);
    }

    public function test_the_donation_form_preselects_the_assigned_church(): void
    {
        $this->actingAs($this->localPastor);

        Livewire::test(CreateDonation::class)
            ->assertSchemaStateSet(['church_id' => $this->local->id]);
    }

    public function test_the_partner_form_preselects_the_assigned_church(): void
    {
        $this->actingAs($this->localPastor);

        Livewire::test(CreatePartner::class)
            ->assertSchemaStateSet(['church_id' => $this->local->id]);
    }

    public function test_the_cellule_form_preselects_the_assigned_church(): void
    {
        $this->actingAs($this->localPastor);

        Livewire::test(CreateCellule::class)
            ->assertSchemaStateSet(['church_id' => $this->local->id]);
    }

    public function test_the_user_form_preselects_the_assigned_church(): void
    {
        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->assertSchemaStateSet(['assigned_church_id' => $admin->assigned_church_id]);
    }

    public function test_the_partnership_report_preselects_the_assigned_church(): void
    {
        $this->actingAs($this->localPastor);

        Livewire::test(PartnershipReport::class)
            ->assertSet('data.church_id', $this->local->id);
    }

    public function test_a_subgroup_pastor_creates_a_local_church_under_his_church(): void
    {
        $subgroup = Church::create([
            'name' => 'CE SOUS-GROUPE TEST',
            'type' => ChurchType::SUB_GROUP,
            'parent_id' => $this->group->id,
        ]);

        $pastor = User::create([
            'name' => 'Pasteur Sous-groupe',
            'email' => 'pasteur.sousgroupe@example.test',
            'password' => Hash::make('azerty123'),
            'assigned_church_id' => $subgroup->id,
            'is_active' => true,
        ]);
        $pastor->syncRoles([RoleName::SUBGROUP_PASTOR->value]);

        $this->actingAs($pastor);

        Livewire::test(CreateChurch::class)
            ->assertSchemaStateSet([
                'type' => ChurchType::LOCAL->value,
                'parent_id' => $subgroup->id,
            ]);
    }

    public function test_a_group_pastor_creates_a_subgroup_under_his_church(): void
    {
        $pastor = User::create([
            'name' => 'Pasteur Groupe',
            'email' => 'pasteur.groupe@example.test',
            'password' => Hash::make('azerty123'),
            'assigned_church_id' => $this->group->id,
            'is_active' => true,
        ]);
        $pastor->syncRoles([RoleName::GROUP_PASTOR->value]);

        $this->actingAs($pastor);

        Livewire::test(CreateChurch::class)
            ->assertSchemaStateSet([
                'type' => ChurchType::SUB_GROUP->value,
                'parent_id' => $this->group->id,
            ]);
    }

    public function test_the_default_falls_back_to_the_first_accessible_church(): void
    {
        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
        $admin->update(['assigned_church_id' => null]);

        $this->actingAs($admin);

        Livewire::test(CreateDonation::class)
            ->assertSchemaStateSet(['church_id' => Church::query()->orderBy('id')->value('id')]);
    }
}
