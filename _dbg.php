<?php
require 'C:/Project/partnership/vendor/autoload.php';
$app = require 'C:/Project/partnership/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CategoryDon;
foreach (CategoryDon::all() as $c) {
    echo $c->name.' => '.($c->group?->value ?? 'NULL')."\n";
}
echo "json group field: ".json_encode(CategoryDon::first()->group?->value)."\n";
