<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CategoryDon;
use App\Enums\CategoryGroup;

foreach (CategoryDon::all() as $c) {
    echo sprintf("%-30s -> %s (%s)\n", $c->name, $c->group?->value, $c->group?->label());
}
echo "Total: ".CategoryDon::count()."\n";
