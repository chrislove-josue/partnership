<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
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
        $sub = Church::create(['name' => 'CE CALAVI TEST', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $local = Church::create(['name' => 'CE COME 2 TEST', 'type' => ChurchType::LOCAL, 'parent_id' => $sub->id]);

        $pastor = User::create([
            'name' => 'Pasteur test',
            'email' => 'pasteur-top-state@example.test',
            'password' => Hash::make('password'),
            'assigned_church_id' => $local->id,
            'is_active' => true,
        ]);
        $pastor->syncRoles([RoleName::LOCAL_PASTOR->value]);

        $component = Livewire::actingAs($pastor)->test(TopPartners::class)->assertOk();

        $this->assertEquals($local->id, (int) ($component->instance()->data['church_id'] ?? 0));
        $this->assertSame($local->name, $component->instance()->getRanking()['church']);
    }

    public function test_subgroup_pastor_defaults_to_his_assigned_church(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $sub = Church::create(['name' => 'CE CALAVI CAS REEL', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        Church::create(['name' => 'CE FIDJROSSE CAS REEL', 'type' => ChurchType::LOCAL, 'parent_id' => $sub->id]);
        Church::create(['name' => 'CE COME 2 CAS REEL', 'type' => ChurchType::LOCAL, 'parent_id' => $sub->id]);

        $pastor = User::create([
            'name' => 'Pasteur cas reel',
            'email' => 'pasteur-top-reel@example.test',
            'password' => Hash::make('password'),
            'assigned_church_id' => $sub->id,
            'is_active' => true,
        ]);
        $pastor->syncRoles([RoleName::SUBGROUP_PASTOR->value]);

        $component = Livewire::actingAs($pastor)->test(TopPartners::class)->assertOk();

        $this->assertSame($sub->id, (int) ($component->instance()->data['church_id'] ?? 0));
        $this->assertSame($sub->name, $component->instance()->getRanking()['church']);
    }

    public function test_changing_the_church_updates_the_ranking(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $sub = Church::create(['name' => 'CE CALAVI REACT', 'type' => ChurchType::SUB_GROUP, 'parent_id' => $group->id]);
        $local = Church::create(['name' => 'CE COME 2 REACT', 'type' => ChurchType::LOCAL, 'parent_id' => $sub->id]);

        $pastor = User::create([
            'name' => 'Pasteur reactif',
            'email' => 'pasteur-top-react@example.test',
            'password' => Hash::make('password'),
            'assigned_church_id' => $sub->id,
            'is_active' => true,
        ]);
        $pastor->syncRoles([RoleName::SUBGROUP_PASTOR->value]);

        $component = Livewire::actingAs($pastor)->test(TopPartners::class)->assertOk();

        $this->assertSame($sub->name, $component->instance()->getRanking()['church']);

        $component->set('data.church_id', (string) $local->id);

        $this->assertSame($local->name, $component->instance()->getRanking()['church']);

        // L'option « toutes les églises » est aussi sélectionnable.
        $component->set('data.church_id', '');
        $this->assertSame('Toutes les églises accessibles', $component->instance()->getRanking()['church']);
    }
}
