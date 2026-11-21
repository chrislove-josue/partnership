<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\NotificationType;
use App\Enums\PeriodType;
use App\Enums\RoleName;
use App\Filament\Resources\Notifications\Pages\EditNotification;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Donation;
use App\Models\MagicToken;
use App\Models\Notification;
use App\Models\Partner;
use App\Models\User;
use App\Services\MagicTokenService;
use App\Services\PartnershipReportExporter;
use App\Services\PartnershipReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function makeUser(string $role, Church $church, string $email): User
    {
        $user = User::create([
            'name' => 'Compte '.$role,
            'email' => $email,
            'password' => Hash::make('azerty123'),
            'assigned_church_id' => $church->id,
            'is_active' => true,
        ]);
        $user->syncRoles([$role]);

        return $user;
    }

    public function test_magic_tokens_expire_within_thirty_minutes(): void
    {
        app(MagicTokenService::class)->generate('magic-expiry@example.test');

        $token = MagicToken::where('email', 'magic-expiry@example.test')->firstOrFail();

        $this->assertGreaterThan(now()->addMinutes(29), $token->expires_at);
        $this->assertLessThanOrEqual(now()->addMinutes(30)->addSeconds(5), $token->expires_at);
    }

    public function test_sync_deletions_are_limited_to_accessible_churches(): void
    {
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $churchA = Church::create(['name' => 'EGLISE SYNC A', 'type' => ChurchType::LOCAL, 'parent_id' => $group->id]);
        $churchB = Church::create(['name' => 'EGLISE SYNC B', 'type' => ChurchType::LOCAL, 'parent_id' => $group->id]);
        $staff = $this->makeUser(RoleName::STAFF->value, $churchA, 'staff-sync@example.test');

        $partnerA = Partner::create(['first_name' => 'Local', 'last_name' => 'A', 'church_id' => $churchA->id]);
        $partnerB = Partner::create(['first_name' => 'Autre', 'last_name' => 'B', 'church_id' => $churchB->id]);
        $partnerIdA = $partnerA->id;
        $partnerIdB = $partnerB->id;

        $partnerA->delete();
        $partnerB->delete();

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/sync/deletions')
            ->assertOk()
            ->assertJsonPath('data.0.entity', 'partners')
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['entity_id' => (string) $partnerIdA])
            ->assertJsonMissing(['entity_id' => (string) $partnerIdB]);

        $this->assertDatabaseHas('sync_deletions', [
            'entity' => 'partners',
            'entity_id' => $partnerIdA,
            'church_id' => $churchA->id,
        ]);
        $this->assertDatabaseHas('sync_deletions', [
            'entity' => 'partners',
            'entity_id' => $partnerIdB,
            'church_id' => $churchB->id,
        ]);
    }

    public function test_a_pastor_cannot_edit_a_notification_addressed_to_someone_else(): void
    {
        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $pastor = $this->makeUser(RoleName::GROUP_PASTOR->value, $church, 'pasteur-notif@example.test');
        $other = $this->makeUser(RoleName::STAFF->value, $church, 'autre-notif@example.test');

        $notification = Notification::create([
            'type' => NotificationType::SYSTEM,
            'title' => 'Notification privée',
            'message' => 'Message privé',
            'user_id' => $other->id,
            'is_read' => false,
        ]);

        $this->assertTrue(Gate::forUser($pastor)->denies('update', $notification));

        $this->actingAs($pastor)
            ->get(EditNotification::getUrl(['record' => $notification]))
            ->assertNotFound();
    }

    public function test_report_export_neutralizes_excel_formulas_from_user_data(): void
    {
        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $category = CategoryDon::firstOrFail();
        $partner = Partner::create([
            'first_name' => '=HYPERLINK("http://evil.test")',
            'last_name' => 'FACTEUR',
            'church_id' => $church->id,
        ]);
        Donation::create([
            'partner_id' => $partner->id,
            'church_id' => $church->id,
            'category_id' => $category->id,
            'amount' => 1000,
            'date' => '2026-11-01 10:00:00',
        ]);

        $report = app(PartnershipReportService::class)
            ->buildReport($church->id, PeriodType::MONTH, 2026, 11);
        $xlsx = app(PartnershipReportExporter::class)->toXlsx($report);

        $path = tempnam(sys_get_temp_dir(), 'rapport-securite').'.xlsx';
        file_put_contents($path, $xlsx);
        $workbook = IOFactory::load($path);
        $value = (string) $workbook->getSheetByName('Partenaires')->getCell('A2')->getValue();

        $this->assertStringStartsWith("'=HYPERLINK", $value);
        @unlink($path);
    }

    public function test_changing_email_requires_the_current_password_and_resets_verification(): void
    {
        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $user = $this->makeUser(RoleName::STAFF->value, $church, 'email-secure@example.test');
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', [
                'name' => $user->name,
                'email' => 'nouvel-email@example.test',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertDatabaseHas('users', ['email' => 'email-secure@example.test']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', [
                'name' => $user->name,
                'email' => 'nouvel-email@example.test',
                'current_password' => 'azerty123',
            ])
            ->assertOk();

        $this->assertDatabaseHas('users', ['email' => 'nouvel-email@example.test', 'email_verified_at' => null]);
    }
}
