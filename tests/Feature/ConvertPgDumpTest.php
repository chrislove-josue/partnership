<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConvertPgDumpTest extends TestCase
{
    private string $directory;

    private string $dumpPath;

    private string $outputPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pg-dump-'.uniqid();
        File::makeDirectory($this->directory);
        $this->dumpPath = $this->directory.DIRECTORY_SEPARATOR.'dump.sql';
        $this->outputPath = $this->directory.DIRECTORY_SEPARATOR.'export.json';

        File::put($this->dumpPath, <<<'SQL'
        INSERT INTO public."Church" (id, name, type, "parentId", "managerId", "createdAt", "updatedAt") VALUES ('c1', 'CE Test', 'GROUP', NULL, NULL, '2026-03-11 17:03:36.053', '2026-03-11 17:03:36.053');
        INSERT INTO public."Notification" (id, type, title, message, data, "isRead", "createdAt", "userId") VALUES ('n1', 'NEW_DONATION', 'Nouveau don', 'L''église a reçu 10 000,50 FCFA', '{"amount": 10000.5, "labels": ["a,b", "c"]}', true, '2026-03-11 17:03:36.053', 'u1');
        INSERT INTO public."Role" (id, name, "createdAt", "updatedAt", "isSystem") VALUES ('r1', 'SUPER_ADMIN', '2026-03-11 17:03:36.145', '2026-03-11 17:03:36.145', true);
        INSERT INTO public."Permission" (id, action, "createdAt", "updatedAt") VALUES ('p1', 'VIEW_DASHBOARD', '2026-03-11 17:03:36.07', '2026-03-11 17:03:36.07');
        INSERT INTO public."_PermissionToRole" ("A", "B") VALUES ('p1', 'r1');
        SQL);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_it_converts_postgres_inserts_to_the_legacy_import_format(): void
    {
        $this->runConverter();

        $export = json_decode(File::get($this->outputPath), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('CE Test', $export['churches'][0]['name']);
        $this->assertNull($export['churches'][0]['parentId']);
        $this->assertSame("L'église a reçu 10 000,50 FCFA", $export['notifications'][0]['message']);
        $this->assertTrue($export['notifications'][0]['isRead']);
        $this->assertSame(10000.5, $export['notifications'][0]['data']['amount']);
        $this->assertSame(['a,b', 'c'], $export['notifications'][0]['data']['labels']);
        $this->assertSame('VIEW_DASHBOARD', $export['rolePermissions'][0]['permissions'][0]['action']);
    }

    public function test_it_backs_up_an_existing_export_before_overwriting_it(): void
    {
        File::put($this->outputPath, 'export précédent');

        $this->runConverter();

        $this->assertSame('export précédent', File::get($this->outputPath.'.bak'));
        $this->assertNotSame('export précédent', File::get($this->outputPath));
    }

    private function runConverter(): void
    {
        $process = new Process([
            PHP_BINARY,
            base_path('database/convert-pg-dump.php'),
            $this->dumpPath,
            '--output='.$this->outputPath,
        ]);

        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }
}
