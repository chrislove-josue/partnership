<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\UploadsXlsx;
use Tests\TestCase;

class ImportTenantSecurityTest extends TestCase
{
    use RefreshDatabase;
    use UploadsXlsx;

    private Church $churchA;
    private Church $churchB;
    private Partner $partnerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $this->churchA = Church::create(['name' => 'EGLISE A IMPORT', 'type' => ChurchType::LOCAL, 'parent_id' => $group->id]);
        $this->churchB = Church::create(['name' => 'EGLISE B IMPORT', 'type' => ChurchType::LOCAL, 'parent_id' => $group->id]);
        $this->partnerB = Partner::create([
            'first_name' => 'Victime',
            'last_name' => 'DUPONT',
            'email' => 'partenaire-b@example.test',
            'phone' => '+22961000099',
            'handle_kc' => '@victimeb',
            'church_id' => $this->churchB->id,
        ]);
    }

    private function staff(Church $church, string $email): User
    {
        $user = User::create([
            'name' => 'Personnel '.$church->name,
            'email' => $email,
            'password' => Hash::make('azerty123'),
            'assigned_church_id' => $church->id,
            'is_active' => true,
        ]);
        $user->syncRoles([RoleName::STAFF->value]);

        return $user;
    }

    public function test_staff_cannot_move_a_partner_from_another_church_through_partner_import(): void
    {
        $staffA = $this->staff($this->churchA, 'staff-a-import@example.test');

        $this->actingAs($staffA, 'sanctum')
            ->post('/api/partners/import', [
                'file' => $this->uploadXlsx(
                    ['firstName', 'lastName', 'email', 'phone', 'handleKC', 'churchName'],
                    [['Intrus', 'TEST', $this->partnerB->email, '', '', $this->churchA->name]],
                ),
            ])
            ->assertOk()
            ->assertJsonPath('imported', 0)
            ->assertJsonPath('error_count', 1);

        $this->assertDatabaseHas('partners', [
            'id' => $this->partnerB->id,
            'first_name' => 'Victime',
            'church_id' => $this->churchB->id,
        ]);
        $this->assertDatabaseMissing('partners', ['email' => $this->partnerB->email, 'church_id' => $this->churchA->id]);
    }

    public function test_staff_cannot_attach_a_donation_to_a_partner_from_another_church(): void
    {
        $staffA = $this->staff($this->churchA, 'staff-a-donation@example.test');

        $this->actingAs($staffA, 'sanctum')
            ->post('/api/donations/import', [
                'file' => $this->uploadXlsx(
                    ['email', 'churchName', 'donationAmount', 'donationDate', 'donationCategoryName'],
                    [[$this->partnerB->email, $this->churchA->name, 5000, '2026-11-01', 'Offrandes']],
                ),
            ])
            ->assertOk()
            ->assertJsonPath('imported', 0)
            ->assertJsonPath('error_count', 1);

        $this->assertSame(0, Donation::where('partner_id', $this->partnerB->id)->count());
        $this->assertDatabaseHas('partners', [
            'id' => $this->partnerB->id,
            'church_id' => $this->churchB->id,
        ]);
    }

    public function test_import_endpoints_require_the_create_permission(): void
    {
        $user = User::create([
            'name' => 'Sans permissions',
            'email' => 'sans-permission-import@example.test',
            'password' => Hash::make('azerty123'),
            'assigned_church_id' => $this->churchA->id,
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->post('/api/partners/import', [
                'file' => $this->uploadXlsx(['firstName', 'lastName', 'churchName'], [['Jean', 'Doe', $this->churchA->name]]),
            ])
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->post('/api/donations/import', [
                'file' => $this->uploadXlsx(['churchName', 'donationAmount', 'donationDate', 'donationCategoryName'], [[$this->churchA->name, 1000, '2026-11-01', 'Dîmes']]),
            ])
            ->assertForbidden();
    }

    public function test_staff_cannot_auto_create_a_church_outside_his_scope(): void
    {
        $staffA = $this->staff($this->churchA, 'staff-a-autocreate@example.test');

        $this->actingAs($staffA, 'sanctum')
            ->post('/api/partners/import', [
                'file' => $this->uploadXlsx(
                    ['firstName', 'lastName', 'email', 'churchName'],
                    [['Nouveau', 'PARTENAIRE', 'nouveau-auto@example.test', 'EGLISE FANTOME']],
                ),
            ])
            ->assertOk()
            ->assertJsonPath('imported', 0);

        $this->assertDatabaseMissing('churches', ['name' => 'EGLISE FANTOME']);
    }
}
