<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== ARBRE REEL ===\n";
foreach (App\Models\Church::orderBy('id')->get() as $c) {
    $indent = str_repeat('  ', $c->ancestorIds() ? count($c->ancestorIds()) : 0);
    echo $indent."{$c->id} | {$c->name} | {$c->type?->value} | parent=".($c->parent_id ?? 'null')."\n";
}
echo "\n=== COMPTES GROUPE / SOUS-GROUPE ===\n";
foreach (App\Models\User::whereNotNull('assigned_church_id')->get() as $u) {
    if (in_array($u->roleName(), ['GROUP_PASTOR','SUBGROUP_PASTOR'], true)) {
        echo "{$u->email} | {$u->roleName()} | ".($u->assignedChurch?->name ?? '?')."\n";
    }
}
