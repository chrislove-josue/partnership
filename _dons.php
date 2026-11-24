<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== DONS PAR EGLISE ===\n";
$rows = Illuminate\Support\Facades\DB::table('donations')
    ->join('churches', 'churches.id', '=', 'donations.church_id')
    ->selectRaw('churches.id, churches.name, count(*) as n, sum(donations.amount) as total')
    ->groupBy('churches.id', 'churches.name')
    ->orderBy('churches.id')
    ->get();
foreach ($rows as $r) {
    echo "{$r->id} | {$r->name} | {$r->n} dons | ".number_format((float)$r->total, 0, ',', ' ')." FCFA\n";
}
echo "total dons: ".App\Models\Donation::count()."\n";
