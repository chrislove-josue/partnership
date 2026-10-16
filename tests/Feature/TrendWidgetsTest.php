<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Filament\Widgets\DonationsByCelluleChart;
use App\Filament\Widgets\DonationsByChurchChart;
use App\Filament\Widgets\DonationsByPartnerChart;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TrendWidgetsTest extends TestCase
{
    use RefreshDatabase;

    public static function widgetProvider(): array
    {
        return [
            'partner' => [DonationsByPartnerChart::class],
            'church' => [DonationsByChurchChart::class],
            'cellule' => [DonationsByCelluleChart::class],
        ];
    }

    #[DataProvider('widgetProvider')]
    public function test_the_trend_widget_renders_for_a_super_admin(string $widget): void
    {
        $this->seed();

        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();

        Livewire::actingAs($admin)
            ->test($widget)
            ->assertOk();
    }

    #[DataProvider('widgetProvider')]
    public function test_the_trend_widget_renders_with_data(string $widget): void
    {
        $this->seed();

        $church = Church::create(['name' => 'Église Test', 'type' => ChurchType::LOCAL]);
        $category = CategoryDon::create(['name' => 'Offrande']);
        $partner = Partner::create([
            'first_name' => 'Jean',
            'last_name' => 'Test',
            'church_id' => $church->id,
        ]);

        Donation::create([
            'amount' => 2500,
            'date' => '2026-04-11',
            'partner_id' => $partner->id,
            'church_id' => $church->id,
            'category_id' => $category->id,
        ]);

        $admin = User::create([
            'name' => 'Admin Trend',
            'email' => 'admin.trend@example.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $admin->syncRoles([RoleName::SUPER_ADMIN->value]);

        Livewire::actingAs($admin)
            ->test($widget)
            ->assertOk();
    }
}
