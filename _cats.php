<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (\App\Models\CategoryDon::orderBy('id')->get() as $c) {
    echo $c->id.' | '.$c->name.' | '.($c->group?->value ?? 'NULL').PHP_EOL;
}
