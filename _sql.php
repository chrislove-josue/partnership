<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$q = App\Models\Church::query()->withTotalDonations();
echo $q->toSql()."\n\n";
$rows = $q->limit(3)->get();
foreach ($rows as $r) {
    echo 'id='.var_export($r->id, true).' total='.var_export($r->total_donations, true)."\n";
}
