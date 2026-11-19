<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Resources\Donations\Pages\ListDonations;
use App\Filament\Resources\Partners\Pages\ListPartners;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportActionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed();
        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        $admin = User::create([
            'name' => 'Admin import',
            'email' => 'admin-import@example.test',
            'password' => Hash::make('password'),
            'assigned_church_id' => $church->id,
            'is_active' => true,
        ]);
        $admin->syncRoles([RoleName::SUPER_ADMIN->value]);

        return $admin;
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

        $path = tempnam(sys_get_temp_dir(), 'import-ui').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        // File::fake() expose la propriété `name` attendue par le helper d'upload Livewire.
        return UploadedFile::fake()->createWithContent('partenaires.xlsx', (string) file_get_contents($path));
    }

    public function test_partner_import_action_imports_the_uploaded_rows(): void
    {
        $this->actingAs($this->admin());

        $file = $this->uploadXlsx(
            ['firstName', 'lastName', 'email', 'phone', 'churchName', 'celluleName', 'donationAmount', 'donationDate', 'donationCategoryName'],
            [
                ['Alice', 'YAO', 'alice@example.test', '61981233', 'CE SIKECODJI', 'Cellule Test', 6500, '2026-02-10', 'Offering UI'],
                ['Bob', 'KOUASSI', 'bob@example.test', '61000002', 'CE SIKECODJI', 'Cellule Test', 3500, '2026-02-11', 'Offering UI'],
            ],
        );

        Livewire::test(ListPartners::class)
            ->call('mountAction', 'importPartners')
            ->set('mountedActions.0.data.file', $file)
            ->call('callMountedAction');

        $this->assertSame(2, Partner::count());
        $this->assertSame(2, Donation::count());
        $this->assertSame(
            ['Alice', 'Bob'],
            Partner::orderBy('id')->pluck('first_name')->all(),
        );
        $this->assertSame(
            'Cellule Test',
            Partner::firstOrFail()->cellule->name,
        );
    }

    public function test_donation_import_action_imports_the_uploaded_rows(): void
    {
        $this->actingAs($this->admin());
        $church = Church::where('name', 'CE SIKECODJI')->firstOrFail();
        Partner::create([
            'first_name' => 'Jean',
            'last_name' => 'Doe',
            'email' => 'jean@example.test',
            'church_id' => $church->id,
        ]);

        $file = $this->uploadXlsx(
            ['email', 'churchName', 'donationAmount', 'donationDate', 'donationCategoryName'],
            [
                ['jean@example.test', 'CE SIKECODJI', 5000, '2026-02-10', 'Tithe UI'],
                ['jean@example.test', 'CE SIKECODJI', 5000, '2026-02-10', 'Tithe UI'],
            ],
        );

        Livewire::test(ListDonations::class)
            ->call('mountAction', 'importDonations')
            ->set('mountedActions.0.data.file', $file)
            ->call('callMountedAction');

        $this->assertSame(1, Donation::count());
        $this->assertSame(1, Partner::count());
        $this->assertSame(5000.0, (float) Donation::firstOrFail()->amount);
    }

    public function test_template_actions_download_the_xlsx_models(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ListPartners::class)
            ->call('mountAction', 'templatePartners')
            ->assertFileDownloaded('modele_partenaires.xlsx');

        Livewire::test(ListDonations::class)
            ->call('mountAction', 'templateDonations')
            ->assertFileDownloaded('modele_dons.xlsx');
    }
}
