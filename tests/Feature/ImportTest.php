<?php

namespace Tests\Feature;

use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Services\Import\DonationImportService;
use App\Services\Import\ImportHelpers;
use App\Services\Import\ImportTemplateGenerator;
use App\Services\Import\PartnerImportService;
use App\Services\Import\SpreadsheetReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, int $churchId, string $email): \App\Models\User
    {
        $user = \App\Models\User::create([
            'name' => 'User '.$role,
            'email' => $email,
            'password' => Hash::make('password'),
            'assigned_church_id' => $churchId,
            'is_active' => true,
        ]);
        $user->syncRoles([$role]);

        return $user;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function uploadXlsx(array $headers, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            'import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    public function test_helpers_normalize_dates_phones_handles_and_amounts(): void
    {
        $helpers = app(ImportHelpers::class);

        $this->assertSame('2026-02-28', $helpers->parseDateFlexible('2026-02-28')?->toDateString());
        $this->assertNull($helpers->parseDateFlexible('2026-02-31'));
        $this->assertNull($helpers->parseDateFlexible('31/02/2026'));
        // Même comportement que la source : MM/JJ/AAAA si le premier nombre est <= 12.
        $this->assertSame('2026-10-02', $helpers->parseDateFlexible('10/02/2026')?->toDateString());
        $this->assertSame('2026-02-25', $helpers->parseDateFlexible('25/02/2026')?->toDateString());
        $this->assertSame('2024-02-29', $helpers->parseDateFlexible('2024-02-29')?->toDateString());
        $this->assertSame('2026-11-19', $helpers->parseDateFlexible(46345.0)?->toDateString());

        $this->assertSame('+22961981233', $helpers->normalizePhone(' 61 98 12 33 '));
        $this->assertSame('+22961981233', $helpers->normalizePhone('22961981233'));
        $this->assertSame('+22961981233', $helpers->normalizePhone('0022961981233'));
        $this->assertNull($helpers->normalizePhone(null));

        $this->assertSame('@nestor229', $helpers->normalizeHandleKC(' nestor229 '));
        $this->assertSame('@Nestor229', $helpers->normalizeHandleKC('@@Nestor 229'));

        $this->assertSame(5000.0, $helpers->parseAmount('5 000'));
        $this->assertSame(5000.0, $helpers->parseAmount('5,000'));
        $this->assertSame(5000.0, $helpers->parseAmount('50x00FCFA'));
        $this->assertNull($helpers->parseAmount('0'));
        $this->assertNull($helpers->parseAmount('abc'));
    }

    public function test_spreadsheet_reader_returns_normalized_rows(): void
    {
        $file = $this->uploadXlsx(
            ['firstName', 'lastName', 'churchName', 'donationAmount'],
            [
                ['Jean', 'Doe', 'CE SIKECODJI', 5000],
                [null, null, null, null],
                ['Marie', 'Dossou', 'AUTRE ÉGLISE', 3000],
            ],
        );

        $rows = app(SpreadsheetReader::class)->read($file);

        $this->assertCount(2, $rows);
        $this->assertSame('Jean', $rows[0]['firstname']);
        $this->assertSame('Doe', $rows[0]['lastname']);
        $this->assertSame('CE SIKECODJI', $rows[0]['churchname']);
        $this->assertSame(5000.0, (float) $rows[0]['donationamount']);
        $this->assertSame('Marie', $rows[1]['firstname']);
        $this->assertSame('AUTRE ÉGLISE', $rows[1]['churchname']);

        @unlink($file->getRealPath());
    }

    public function test_partner_import_auto_creates_church_cellule_category_and_partner(): void
    {
        $this->seed();
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $admin = $this->makeUser(RoleName::SUPER_ADMIN->value, $group->id, 'admin@example.test');

        $rows = [[
            'firstname' => 'Nestor',
            'lastname' => 'ATCHOUGO',
            'email' => 'nestor@example.test',
            'phone' => '61981233',
            'handlekc' => 'nestor229',
            'churchname' => 'EGLISE TEST',
            'cellulename' => 'Cellule Alpha',
            'address' => 'Cotonou',
            'birthdate' => '1990-05-12',
            'donationamount' => '5 000',
            'donationdate' => '2026-02-10',
            'donationcategoryname' => 'Offering Import',
        ]];

        $result = app(PartnerImportService::class)->import($admin, $rows);

        $this->assertSame(1, $result['success']);
        $this->assertSame(0, $result['errors']);
        $this->assertSame([], $result['errorDetails']);

        $church = Church::where('name', 'EGLISE TEST')->firstOrFail();
        $this->assertSame(ChurchType::LOCAL, $church->type);
        $this->assertSame($group->id, $church->parent_id);
        $this->assertSame('Cellule Alpha', $church->cellules()->firstOrFail()->name);

        $partner = Partner::where('email', 'nestor@example.test')->firstOrFail();
        $this->assertSame('Nestor', $partner->first_name);
        $this->assertSame('+22961981233', $partner->phone);
        $this->assertSame('@nestor229', $partner->handle_kc);
        $this->assertSame('1990-05-12', $partner->birthdate?->toDateString());
        $this->assertSame($church->id, $partner->church_id);
        $this->assertSame($church->cellules()->firstOrFail()->id, $partner->cellule_id);

        $donation = Donation::firstOrFail();
        $this->assertSame(5000.0, (float) $donation->amount);
        $this->assertSame('2026-02-10', $donation->date->toDateString());
        $this->assertSame('Offering Import', $donation->category->name);
        $this->assertSame('#1F4E78', $donation->category->color);
    }

    public function test_partner_import_updates_an_existing_partner_found_by_phone(): void
    {
        $this->seed();
        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $admin = $this->makeUser(RoleName::SUPER_ADMIN->value, $church->id, 'admin@example.test');
        $existing = Partner::create([
            'first_name' => 'Ancien',
            'last_name' => 'Nom',
            'phone' => '+22961981233',
            'church_id' => $church->id,
        ]);

        $result = app(PartnerImportService::class)->import($admin, [[
            'firstname' => 'Nouveau',
            'lastname' => 'Prenom',
            'phone' => '0022961981233',
            'churchname' => $church->name,
        ]]);

        $this->assertSame(1, $result['success']);
        $this->assertSame(1, Partner::count());
        $existing->refresh();
        $this->assertSame('Nouveau', $existing->first_name);
        $this->assertSame('Prenom', $existing->last_name);
    }

    public function test_partner_import_rejects_a_strictly_invalid_donation_date(): void
    {
        $this->seed();
        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $admin = $this->makeUser(RoleName::SUPER_ADMIN->value, $church->id, 'admin@example.test');

        $result = app(PartnerImportService::class)->import($admin, [[
            'firstname' => 'Jean',
            'lastname' => 'Doe',
            'churchname' => $church->name,
            'donationamount' => 5000,
            'donationdate' => '2026-02-31',
            'donationcategoryname' => 'Offering',
        ]]);

        $this->assertSame(0, $result['success']);
        $this->assertSame(1, $result['errors']);
        $this->assertStringContainsString('Date de don invalide', $result['errorDetails'][0]);
        $this->assertSame(0, Donation::count());
    }

    public function test_donation_import_creates_missing_partner_and_skips_duplicates(): void
    {
        $this->seed();
        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $admin = $this->makeUser(RoleName::SUPER_ADMIN->value, $church->id, 'admin@example.test');

        $row = [
            'firstname' => 'Marie',
            'lastname' => 'Dossou',
            'email' => 'marie@example.test',
            'churchname' => $church->name,
            'donationamount' => '5 000',
            'donationdate' => '2026-02-10',
            'donationcategoryname' => 'Tithe',
        ];

        $service = app(DonationImportService::class);
        $first = $service->import($admin, [$row, $row]);

        $this->assertSame(1, $first['success']);
        $this->assertSame(1, $first['skipped']);
        $this->assertSame(0, $first['errors']);
        $this->assertSame(2, $first['total']);
        $this->assertSame(1, Donation::count());
        $this->assertSame(1, Partner::count());

        $second = $service->import($admin, [$row]);
        $this->assertSame(0, $second['success']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(1, Donation::count());
    }

    public function test_donation_import_auto_creates_church_and_category_with_case_insensitive_lookup(): void
    {
        $this->seed();
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $admin = $this->makeUser(RoleName::SUPER_ADMIN->value, $group->id, 'admin@example.test');

        $service = app(DonationImportService::class);
        $result = $service->import($admin, [
            [
                'firstname' => 'Jean',
                'lastname' => 'Doe',
                'email' => 'jean@example.test',
                'church' => 'eglise nouvelle',
                'donationamount' => 2500,
                'donationdate' => '10/02/2026',
                'donationcategoryname' => 'Offering',
            ],
            [
                'firstname' => 'Marie',
                'lastname' => 'Dossou',
                'email' => 'marie@example.test',
                'church' => 'EGLISE NOUVELLE',
                'donationamount' => 1500,
                'donationdate' => '2026-02-11',
                'donationcategoryname' => 'offering',
            ],
        ]);

        $this->assertSame(2, $result['success']);
        $this->assertSame(0, $result['errors']);
        $this->assertSame(2, Church::count());
        $this->assertSame(1, \App\Models\CategoryDon::where('name', 'Offering')->count());
        $church = Church::where('name', 'eglise nouvelle')->firstOrFail();
        $this->assertSame($group->id, $church->parent_id);
        $this->assertSame(2, Donation::where('church_id', $church->id)->count());
    }

    public function test_donation_import_refuses_a_church_outside_the_actor_scope(): void
    {
        $this->seed();
        $group = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $own = Church::create(['name' => 'MA LOCAL', 'type' => ChurchType::LOCAL, 'parent_id' => $group->id]);
        $other = Church::create(['name' => 'AUTRE LOCAL', 'type' => ChurchType::LOCAL, 'parent_id' => $group->id]);
        $staff = $this->makeUser(RoleName::STAFF->value, $own->id, 'staff@example.test');

        $result = app(DonationImportService::class)->import($staff, [[
            'firstname' => 'Jean',
            'lastname' => 'Doe',
            'email' => 'jean@example.test',
            'churchname' => $other->name,
            'donationamount' => 5000,
            'donationdate' => '2026-02-10',
            'donationcategoryname' => 'Offering',
        ]]);

        $this->assertSame(0, $result['success']);
        $this->assertSame(1, $result['errors']);
        $this->assertStringContainsString('Permission refusée pour l\'église AUTRE LOCAL', $result['errorDetails'][0]);
        $this->assertSame(0, Donation::count());
    }

    public function test_template_generator_exposes_headers_and_help_sheet(): void
    {
        $xlsx = app(ImportTemplateGenerator::class)->toXlsx();
        $path = tempnam(sys_get_temp_dir(), 'modele').'.xlsx';
        file_put_contents($path, $xlsx);

        try {
            $workbook = IOFactory::load($path);

            $this->assertSame(['Dons', 'Aide'], $workbook->getSheetNames());
            $sheet = $workbook->getSheetByName('Dons');
            $this->assertSame('firstName', $sheet->getCell('A1')->getValue());
            $this->assertSame('donationCategoryName', $sheet->getCell('I1')->getValue());
            $this->assertSame('Nestor', $sheet->getCell('A2')->getValue());
            $this->assertSame('Colonne', $workbook->getSheetByName('Aide')->getCell('A1')->getValue());
        } finally {
            @unlink($path);
        }
    }
}
