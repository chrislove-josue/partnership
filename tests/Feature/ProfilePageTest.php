<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_displays_phone_and_kingschat_fields_with_full_layout(): void
    {
        $this->seed();
        $user = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();

        $this->actingAs($user)
            ->get('/dashboard/profile')
            ->assertOk()
            ->assertSee('Téléphone')
            ->assertSee('Identifiant KingsChat (handle KC)')
            ->assertSee('Tableau de bord');
    }

    public function test_user_can_update_phone_and_kingschat_handle_from_profile(): void
    {
        $this->seed();
        $user = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->assertFormSet([
                'phone' => $user->phone,
                'handle_kc' => $user->handle_kc,
            ])
            ->fillForm([
                'phone' => '+229 98 12 34 56',
                'handle_kc' => 'mon_handle_kc',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => '+229 98 12 34 56',
            'handle_kc' => 'mon_handle_kc',
        ]);
    }

    public function test_profile_rejects_a_kingschat_handle_already_used(): void
    {
        $this->seed();
        $user = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
        User::factory()->create(['handle_kc' => 'handle_deja_pris']);

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->fillForm(['handle_kc' => 'handle_deja_pris'])
            ->call('save')
            ->assertHasFormErrors(['handle_kc']);
    }
}
