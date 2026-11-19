<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Filament\Resources\CategoryDons\Pages\CreateCategoryDon;
use App\Filament\Resources\CategoryDons\Pages\EditCategoryDon;
use App\Filament\Resources\Cellules\Pages\CreateCellule;
use App\Filament\Resources\Cellules\Pages\EditCellule;
use App\Filament\Resources\Churches\ChurchResource;
use App\Filament\Resources\Churches\Pages\CreateChurch;
use App\Filament\Resources\Churches\Pages\EditChurch;
use App\Filament\Resources\Concerns\RedirectsToListAfterSave;
use App\Filament\Resources\Donations\Pages\CreateDonation;
use App\Filament\Resources\Donations\Pages\EditDonation;
use App\Filament\Resources\Notifications\Pages\CreateNotification;
use App\Filament\Resources\Notifications\Pages\EditNotification;
use App\Filament\Resources\Partners\Pages\CreatePartner;
use App\Filament\Resources\Partners\Pages\EditPartner;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RedirectAfterSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_create_and_edit_page_returns_to_the_list(): void
    {
        $pages = [
            CreateCategoryDon::class, EditCategoryDon::class,
            CreateCellule::class, EditCellule::class,
            CreateChurch::class, EditChurch::class,
            CreateDonation::class, EditDonation::class,
            CreateNotification::class, EditNotification::class,
            CreatePartner::class, EditPartner::class,
            CreateRole::class, EditRole::class,
            CreateUser::class, EditUser::class,
        ];

        foreach ($pages as $page) {
            $this->assertContains(
                RedirectsToListAfterSave::class,
                class_uses_recursive($page),
                "La page {$page} doit revenir à la liste après enregistrement.",
            );
        }
    }

    public function test_creating_a_church_redirects_to_the_list(): void
    {
        $this->seed();
        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();

        Livewire::actingAs($admin)
            ->test(CreateChurch::class)
            ->fillForm([
                'name' => 'CE NOUVEAU',
                'type' => ChurchType::LOCAL->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(ChurchResource::getUrl('index'));
    }

    public function test_updating_a_church_redirects_to_the_list(): void
    {
        $this->seed();
        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(EditChurch::class, ['record' => $church->id])
            ->fillForm(['name' => 'CE SIKECODJI MODIFIE'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(ChurchResource::getUrl('index'));
    }
}
