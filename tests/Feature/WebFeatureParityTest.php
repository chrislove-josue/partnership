<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Pages\LoginHistory;
use App\Filament\Pages\PartnershipReport;
use App\Filament\Resources\Pastors\Pages\ListPastors;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Donation;
use App\Models\LoginHistory as LoginHistoryModel;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WebFeatureParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_pastors_list_only_contains_pastors(): void
    {
        $this->seed();

        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
        $church = Church::query()->firstOrFail();

        $pastor = User::factory()->create(['assigned_church_id' => $church->id]);
        $pastor->syncRoles([RoleName::LOCAL_PASTOR->value]);

        $staff = User::factory()->create(['assigned_church_id' => $church->id]);
        $staff->syncRoles([RoleName::STAFF->value]);

        Livewire::actingAs($admin)
            ->test(ListPastors::class)
            ->assertCanSeeTableRecords([$pastor])
            ->assertCanNotSeeTableRecords([$staff]);
    }

    public function test_login_history_can_be_filtered_by_source_and_search(): void
    {
        $this->seed();

        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
        $mobile = LoginHistoryModel::create([
            'user_id' => $admin->id,
            'event' => LoginHistoryModel::EVENT_LOGIN,
            'source' => LoginHistoryModel::SOURCE_MOBILE,
            'created_at' => now(),
        ]);
        $web = LoginHistoryModel::create([
            'user_id' => $admin->id,
            'event' => LoginHistoryModel::EVENT_LOGOUT,
            'source' => LoginHistoryModel::SOURCE_WEB,
            'created_at' => now(),
        ]);

        $component = Livewire::actingAs($admin)->test(LoginHistory::class);
        $component->set('filters.source', LoginHistoryModel::SOURCE_MOBILE);
        $entries = $component->instance()->getEntries();

        $this->assertTrue($entries->contains(fn (LoginHistoryModel $entry): bool => $entry->id === $mobile->id));
        $this->assertFalse($entries->contains(fn (LoginHistoryModel $entry): bool => $entry->id === $web->id));

        $component->set('filters.source', '')->set('filters.search', 'mobile');
        $entries = $component->instance()->getEntries();

        $this->assertTrue($entries->contains(fn (LoginHistoryModel $entry): bool => $entry->id === $mobile->id));
        $this->assertFalse($entries->contains(fn (LoginHistoryModel $entry): bool => $entry->id === $web->id));
    }

    public function test_report_search_filters_the_displayed_tables_and_shows_donation_details(): void
    {
        $this->seed();

        $admin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
        $church = Church::query()->firstOrFail();
        $category = CategoryDon::query()->firstOrFail();

        $alice = Partner::create([
            'first_name' => 'Alice',
            'last_name' => 'A',
            'church_id' => $church->id,
        ]);
        $bruno = Partner::create([
            'first_name' => 'Bruno',
            'last_name' => 'B',
            'church_id' => $church->id,
        ]);

        Donation::create([
            'amount' => 10000,
            'date' => '2026-11-05',
            'partner_id' => $alice->id,
            'church_id' => $church->id,
            'category_id' => $category->id,
        ]);
        Donation::create([
            'amount' => 20000,
            'date' => '2026-11-06',
            'partner_id' => $bruno->id,
            'church_id' => $church->id,
            'category_id' => $category->id,
        ]);

        $component = Livewire::actingAs($admin)->test(PartnershipReport::class);
        $component
            ->set('data.church_id', $church->id)
            ->set('data.type', 'month')
            ->set('data.month', 11)
            ->set('data.year', 2026)
            ->set('data.report_search', 'Alice')
            ->assertSee('Détail des dons');

        $report = $component->instance()->getReport();

        $this->assertCount(1, $report['partners']);
        $this->assertSame('Alice A', $report['partners'][0]['name']);
        $this->assertCount(1, $report['donations']);
        $this->assertSame('Alice A', $report['donations'][0]['partner']);
    }
}
