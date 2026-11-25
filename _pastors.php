<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\User::whereNotNull('assigned_church_id')->whereHas('roles', fn($q) => $q->whereIn('name', ['GROUP_PASTOR','SUBGROUP_PASTOR','LOCAL_PASTOR']))->get() as $u) {
    echo "{$u->email} | {$u->roleName()} | ".($u->assignedChurch?->name ?? '?')." | type=".($u->assignedChurch?->type?->value ?? '?')."\n";
}
