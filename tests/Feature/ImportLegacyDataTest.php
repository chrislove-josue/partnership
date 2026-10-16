<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Notification;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ImportLegacyDataTest extends TestCase
{
    use RefreshDatabase;

    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $fixture = [
            'churches' => [
                [
                    'id' => 'church_group', 'name' => 'CE SIKECODJI', 'type' => 'GROUP',
                    'parentId' => null, 'managerId' => 'user_super',
                    'createdAt' => '2026-03-11T17:03:36.053Z', 'updatedAt' => '2026-03-11T17:03:36.053Z',
                ],
                [
                    'id' => 'church_local', 'name' => 'CE LOCAL', 'type' => 'LOCAL',
                    'parentId' => 'church_group', 'managerId' => null,
                    'createdAt' => '2026-03-12T11:00:00.000Z', 'updatedAt' => '2026-03-12T11:00:00.000Z',
                ],
            ],
            'roles' => [
                ['id' => 'role_super', 'name' => 'SUPER_ADMIN'],
                ['id' => 'role_local', 'name' => 'LOCAL_PASTOR'],
            ],
            'users' => [
                [
                    'id' => 'user_super', 'email' => 'ancien.admin@example.test', 'name' => 'Ancien Admin',
                    'roleId' => 'role_super', 'assignedChurchId' => 'church_group', 'isActive' => true,
                    'createdAt' => '2026-03-11T17:03:36.198Z', 'updatedAt' => '2026-03-11T17:03:36.198Z',
                    'emailVerified' => null, 'phone' => null, 'handleKC' => null,
                    // Hachage $2b$ (bcryptjs) de "azerty123" : doit être normalisé en $2y$.
                    'password' => '$2b$10$DPjZkbRZc4LKRvawiYvrCuwBPDch0uQ2Q0IwFodWUqBEuHfZK6kYa',
                ],
                [
                    'id' => 'user_local', 'email' => 'ancien.local@example.test', 'name' => 'Ancien Local',
                    'roleId' => 'role_local', 'assignedChurchId' => 'church_local', 'isActive' => true,
                    'createdAt' => '2026-03-12T11:00:00.000Z', 'updatedAt' => '2026-03-12T11:00:00.000Z',
                    'emailVerified' => null, 'phone' => '+22990000000', 'handleKC' => null,
                    'password' => '$2y$10$QCjd8kW1/rr4N5CzJMZ8mON09ipI0CkC16G6hsliMsFWoPH1mnZi.',
                ],
            ],
            'categoryDons' => [
                ['id' => 'cat_tithe', 'name' => 'Tithe', 'color' => '#10B981'],
            ],
            'cellules' => [
                [
                    'id' => 'cellule_1', 'name' => 'Cellule Test', 'churchId' => 'church_group',
                    'leaderId' => 'partner_1',
                    'createdAt' => '2026-03-12T12:00:00.000Z', 'updatedAt' => '2026-03-12T12:00:00.000Z',
                ],
            ],
            'partners' => [
                [
                    'id' => 'partner_1', 'firstName' => 'Jean', 'lastName' => 'Doe',
                    'email' => 'jean.doe@example.test', 'phone' => null, 'handleKC' => null,
                    'address' => null, 'birthdate' => null, 'celluleId' => 'cellule_1',
                    'churchId' => 'church_group',
                    'createdAt' => '2026-03-12T12:00:00.000Z', 'updatedAt' => '2026-03-12T12:00:00.000Z',
                ],
            ],
            'donations' => [
                [
                    'id' => 'don_1', 'amount' => 25000, 'date' => '2026-04-07T23:00:00.000Z',
                    'partnerId' => 'partner_1', 'churchId' => 'church_group', 'categoryId' => 'cat_tithe',
                    'createdAt' => '2026-04-07T23:00:00.000Z', 'updatedAt' => '2026-04-07T23:00:00.000Z',
                ],
            ],
            'notifications' => [
                [
                    'id' => 'notif_1', 'type' => 'NEW_DONATION', 'title' => 'Nouveau don',
                    'message' => 'Don de test', 'data' => ['amount' => 25000], 'isRead' => false,
                    'userId' => 'user_super', 'createdAt' => '2026-04-07T23:00:00.000Z',
                ],
            ],
        ];

        $this->fixturePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'legacy-fixture-'.uniqid().'.json';
        File::put($this->fixturePath, json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    protected function tearDown(): void
    {
        File::delete($this->fixturePath);

        parent::tearDown();
    }

    public function test_it_imports_legacy_data_with_correct_relationships(): void
    {
        $this->artisan('legacy:import', ['--path' => $this->fixturePath, '--force' => true])
            ->assertExitCode(0);

        $this->assertSame(2, Church::count());
        $this->assertSame(1, Partner::count());
        $this->assertSame(1, Donation::count());
        $this->assertSame(1, Notification::count());

        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $local = Church::where('name', 'CE LOCAL')->firstOrFail();

        // Hiérarchie parent/enfant
        $this->assertNull($group->parent_id);
        $this->assertSame($group->id, $local->parent_id);

        // Responsable d'église
        $superAdmin = User::where('email', 'ancien.admin@example.test')->firstOrFail();
        $this->assertSame($superAdmin->id, $group->manager_id);

        // Rôles
        $this->assertTrue($superAdmin->hasRole(RoleName::SUPER_ADMIN->value));
        $localPastor = User::where('email', 'ancien.local@example.test')->firstOrFail();
        $this->assertTrue($localPastor->hasRole(RoleName::LOCAL_PASTOR->value));

        // Hachage bcrypt $2b$ normalisé et vérifiable par Laravel
        $this->assertTrue(Hash::check('azerty123', $superAdmin->password));

        // Partenaire -> cellule + église
        $partner = Partner::firstOrFail();
        $this->assertSame($group->id, $partner->church_id);
        $this->assertSame('Cellule Test', $partner->cellule->name);
        $this->assertSame($partner->id, $partner->cellule->leader_id);

        // Don -> références
        $donation = Donation::firstOrFail();
        $this->assertSame($partner->id, $donation->partner_id);
        $this->assertSame('Tithe', $donation->category->name);
        $this->assertEquals(25000, $donation->amount);

        // Notification -> utilisateur
        $this->assertSame($superAdmin->id, Notification::firstOrFail()->user_id);
    }
}
