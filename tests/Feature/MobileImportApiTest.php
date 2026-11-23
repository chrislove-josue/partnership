<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\UploadsXlsx;
use Tests\TestCase;

class MobileImportApiTest extends TestCase
{
    use RefreshDatabase;
    use UploadsXlsx;

    private function superAdmin(): User
    {
        $this->seed();

        return User::where('email', 'sounesatchougo@gmail.com')->firstOrFail();
    }

    public function test_mobile_can_download_import_templates(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user, 'sanctum')
            ->get('/api/donations/template')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('content-disposition', 'attachment; filename=modele_dons.xlsx');

        $this->actingAs($user, 'sanctum')
            ->get('/api/partners/template')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('content-disposition', 'attachment; filename=modele_partenaires.xlsx');
    }

    public function test_mobile_donation_import_uses_the_web_rules_and_skips_duplicates(): void
    {
        $user = $this->superAdmin();
        $church = Church::query()->where('name', 'CE SIKECODJI')->firstOrFail();

        $headers = ['firstName', 'lastName', 'email', 'phone', 'handleKC', 'churchName', 'donationAmount', 'donationDate', 'donationCategoryName'];
        $row = ['Nestor', 'ATCHOUGO', 'mobile-import@example.test', '+22961981233', '@nestor229', $church->name, 5000, '2026-11-01', 'Offrandes'];

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/donations/import', [
                'file' => $this->uploadXlsx($headers, [$row]),
            ])
            ->assertOk()
            ->assertJsonPath('imported', 1)
            ->assertJsonPath('skipped', 0)
            ->assertJsonPath('error_count', 0)
            ->assertJsonPath('total', 1);

        $this->assertStringContainsString('Import des dons terminé', $response->json('message'));

        $partner = Partner::query()->where('email', 'mobile-import@example.test')->firstOrFail();
        $this->assertDatabaseHas('donations', [
            'partner_id' => $partner->id,
            'church_id' => $church->id,
            'amount' => 5000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->post('/api/donations/import', [
                'file' => $this->uploadXlsx($headers, [$row]),
            ])
            ->assertOk()
            ->assertJsonPath('imported', 0)
            ->assertJsonPath('skipped', 1)
            ->assertJsonPath('error_count', 0);

        $this->assertSame(1, Donation::query()->where('partner_id', $partner->id)->count());
    }

    public function test_mobile_partner_import_creates_partner_cellule_and_optional_donation(): void
    {
        $user = $this->superAdmin();
        $church = Church::query()->where('name', 'CE SIKECODJI')->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->post('/api/partners/import', [
                'file' => $this->uploadXlsx(
                    ['firstName', 'lastName', 'email', 'phone', 'handleKC', 'churchName', 'celluleName', 'address', 'birthdate', 'donationAmount', 'donationDate', 'donationCategoryName'],
                    [['Marie', 'DOSSOU', 'mobile-partner@example.test', '+22961000002', '@marie2', $church->name, 'Cellule Mobile', 'Cotonou', '1990-05-12', 3000, '2026-11-02', 'Dîmes']],
                ),
            ])
            ->assertOk()
            ->assertJsonPath('imported', 1)
            ->assertJsonPath('error_count', 0)
            ->assertJsonPath('total', 1);

        $partner = Partner::query()->where('email', 'mobile-partner@example.test')->firstOrFail();
        $this->assertSame('Cellule Mobile', $partner->cellule?->name);
        $this->assertSame('1990-05-12', $partner->birthdate?->toDateString());
        $this->assertSame(1, Donation::query()->where('partner_id', $partner->id)->count());
    }

    public function test_import_endpoints_require_authentication(): void
    {
        $this->postJson('/api/donations/import')->assertUnauthorized();
        $this->getJson('/api/partners/template')->assertUnauthorized();
    }
}
