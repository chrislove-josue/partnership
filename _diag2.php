<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$u = \App\Models\User::where('email', 'wwashington@gmail.com')->first();
if (!$u) { echo "user not found\n"; exit; }
$access = app(\App\Services\ChurchAccessService::class);
echo "assigned: ".($u->assigned_church_id ?? 'null')."\n";
echo "default: ".$access->defaultChurchId($u)."\n";
echo "accessible: ".implode(',', $access->accessibleChurchIds($u))."\n";
foreach (\App\Models\Church::whereIn('id', $access->accessibleChurchIds($u))->get() as $c) {
    echo "  {$c->id} | {$c->name} | {$c->type?->value} | parent=".($c->parent_id ?? 'null')."\n";
}
