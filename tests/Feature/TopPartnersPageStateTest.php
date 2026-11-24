<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Pages\TopPartners;
use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class TopPartnersPageStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_default_church_is_the_assigned_one(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $sub = Church::create(['name' => 'CE CALAVI TEST', 'type' => \App\Enums\ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $local = Church::create(['name' => 'CE COME 2 TEST', 'type' => \App\Enums\ChurchType::LOCAL, 'parent_id' => $sub->id]);

        $pastor = User::create([
            'name' => 'Pasteur test',
            'email' => 'pasteur-top-state@example.test',
            'password' => Hash::make('password'),
            'assigned_church_id' => $local->id,
            'is_active' => true,
        ]);
        $pastor->syncRoles([RoleName::SUBGROUP_PASTOR->value]);

        $component = Livewire::actingAs($pastor)->test(TopPartners::class);

        $component->assertOk();

        $state = $component->instance()->data;

        fwrite(STDERR, "\nSTATE church_id=".var_export($state['church_id'] ?? null, true)."\n");
        fwrite(STDERR, 'RANKING church='.var_export($component->instance()->getRanking()['church'] ?? null, true)."\n");

        // Pasteur de sous-groupe : le défaut est sa première église fille.
        $this->assertEquals($local->id, (int) ($state['church_id'] ?? 0));
        $this->assertSame($local->name, $component->instance()->getRanking()['church']);
    }

    public function test_subgroup_pastor_defaults_to_first_child_church(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $sub = Church::create(['name' => 'CE CALAVI CAS REEL', 'type' => \App\Enums\ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $child1 = Church::create(['name' => 'CE FIDJROSSE CAS REEL', 'type' => \App\Enums\ChurchType::LOCAL, 'parent_id' => $sub->id]);
        Church::create(['name' => 'CE COME 2 CAS REEL', 'type' => \App\Enums\ChurchType::LOCAL, 'parent_id' => $sub->id]);

        $pastor = User::create([
            'name' => 'Pasteur cas reel',
            'email' => 'pasteur-top-reel@example.test',
            'password' => Hash::make('password'),
            'assigned_church_id' => $sub->id,
            'is_active' => true,
        ]);
        $pastor->syncRoles([RoleName::SUBGROUP_PASTOR->value]);

        $component = Livewire::actingAs($pastor)->test(TopPartners::class)->assertOk();

        $this->assertSame($child1->id, (int) ($component->instance()->data['church_id'] ?? 0));
        $this->assertSame($child1->name, $component->instance()->getRanking()['church']);
    }

    public function test_changing_the_church_updates_the_ranking(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $sub = Church::create(['name' => 'CE CALAVI REACT', 'type' => \App\Enums\ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $local = Church::create(['name' => 'CE COME 2 REACT', 'type' => \App\Enums\ChurchType::LOCAL, 'parent_id' => $sub->id]);

        $pastor = User::create([
            'name' => 'Pasteur reactif',
            'email' => 'pasteur-top-react@example.test',
            'password' => Hash::make('password'),
            'assigned_church_id' => $sub->id,
            'is_active' => true,
        ]);
        $pastor->syncRoles([RoleName::SUBGROUP_PASTOR->value]);

        $component = Livewire::actingAs($pastor)->test(TopPartners::class)->assertOk();

        $this->assertSame($local->name, $component->instance()->getRanking()['church']);

        $component->set('data.church_id', (string) $sub->id);

        $this->assertSame($sub->name, $component->instance()->getRanking()['church']);
    }
}
