<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Basculer sur SQLite mémoire pour simuler les tests
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
DB::purge('sqlite');
$kernel->bootstrap();
Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);
Artisan::call('db:seed', ['--force' => true]);
echo "before: ".App\Models\Church::count()." churches\n";
$code = Artisan::call('church:restructure', ['--force' => true]);
echo "exit=$code\n".Artisan::output();
echo "after: ".App\Models\Church::count()." churches\n";
