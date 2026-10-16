<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Filament\Widgets\TopChurchesWidget;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TopChurchesWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_widget_renders_for_a_super_admin(): void
    {
        $this->seed();

        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();

        Livewire::actingAs($admin)
            ->test(TopChurchesWidget::class)
            ->assertOk();
    }

    public function test_it_lists_the_five_churches_with_the_most_donations(): void
    {
        $this->seed();

        $category = CategoryDon::create(['name' => 'Offrande']);
        $churches = [];

        foreach ([1000, 2000, 3000, 4000, 5000, 6000] as $amount) {
            $church = Church::create([
                'name' => 'Top Church '.$amount,
                'type' => ChurchType::LOCAL,
            ]);
            $partner = Partner::create([
                'first_name' => 'Donateur',
                'last_name' => (string) $amount,
                'church_id' => $church->id,
            ]);

            Donation::create([
                'amount' => $amount,
                'date' => '2026-04-10',
                'partner_id' => $partner->id,
                'church_id' => $church->id,
                'category_id' => $category->id,
            ]);

            $churches[$amount] = $church;
        }

        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();

        Livewire::actingAs($admin)
            ->test(TopChurchesWidget::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$churches[6000], $churches[5000]])
            ->assertCanNotSeeTableRecords([$churches[1000]]);
    }

    public function test_it_scopes_churches_by_the_actor_permissions(): void
    {
        $this->seed();

        $localChurch = Church::firstOrFail();
        $category = CategoryDon::firstOrCreate(['name' => 'Offrande']);

        $pastor = User::create([
            'name' => 'Pasteur Local',
            'email' => 'pastor.top@example.test',
            'password' => bcrypt('password'),
            'assigned_church_id' => $localChurch->id,
            'is_active' => true,
        ]);
        $pastor->syncRoles([RoleName::LOCAL_PASTOR->value]);

        $partner = Partner::create([
            'first_name' => 'Membre',
            'last_name' => 'Local',
            'church_id' => $localChurch->id,
        ]);

        Donation::create([
            'amount' => 1234,
            'date' => '2026-04-10',
            'partner_id' => $partner->id,
            'church_id' => $localChurch->id,
            'category_id' => $category->id,
        ]);

        Livewire::actingAs($pastor)
            ->test(TopChurchesWidget::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$localChurch]);
    }
}
