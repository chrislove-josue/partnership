<?php

namespace Tests\Feature;

use App\Filament\Resources\Cellules\CelluleResource;
use App\Filament\Resources\Churches\ChurchResource;
use App\Filament\Resources\Donations\DonationResource;
use App\Filament\Resources\Notifications\NotificationResource;
use App\Filament\Resources\Partners\PartnerResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FilamentScopingQueryTest extends TestCase
{
    use RefreshDatabase;

    public static function resourceProvider(): array
    {
        return [
            'churches' => [ChurchResource::class],
            'users' => [UserResource::class],
            'partners' => [PartnerResource::class],
            'cellules' => [CelluleResource::class],
            'donations' => [DonationResource::class],
            'notifications' => [NotificationResource::class],
        ];
    }

    #[DataProvider('resourceProvider')]
    public function test_scoped_resource_query_is_valid_for_super_admin(string $resource): void
    {
        $this->seed();

        $admin = User::role(\App\Enums\RoleName::SUPER_ADMIN->value)->firstOrFail();
        $this->actingAs($admin);

        $resource::getEloquentQuery()->count();

        $this->assertTrue(true);
    }

    #[DataProvider('resourceProvider')]
    public function test_scoped_resource_query_is_valid_for_local_pastor(string $resource): void
    {
        $this->seed();

        $church = \App\Models\Church::firstOrFail();

        $pastor = User::create([
            'name' => 'Pasteur Local',
            'email' => 'local.'.strtolower(class_basename($resource)).'@example.test',
            'password' => bcrypt('password'),
            'assigned_church_id' => $church->id,
            'is_active' => true,
        ]);
        $pastor->syncRoles([\App\Enums\RoleName::LOCAL_PASTOR->value]);

        $this->actingAs($pastor);

        $resource::getEloquentQuery()->count();

        $this->assertTrue(true);
    }
}
