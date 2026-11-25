<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== EGLISES ===\n";
foreach (App\Models\Church::orderBy('id')->get() as $c) {
    echo "{$c->id} | {$c->name} | {$c->type?->value} | parent=".($c->parent_id ?? 'null')." | manager=".($c->manager_id ?? 'null')." | active=".($c->is_active ? '1' : '0')."\n";
}
echo "\n=== UTILISATEURS AVEC EGLISE ===\n";
foreach (App\Models\User::whereNotNull('assigned_church_id')->orderBy('id')->get() as $u) {
    echo "{$u->id} | {$u->email} | {$u->roleName()} | church={$u->assigned_church_id} (".($u->assignedChurch?->name ?? '?').")\n";
}
echo "\n=== DONS PAR EGLISE ===\n";
foreach (Illuminate\Support\Facades\DB::table('donations')->selectRaw('church_id, count(*) n, sum(amount) t')->groupBy('church_id')->get() as $d) {
    echo "{$d->church_id} | {$d->n} dons | ".number_format((float)$d->t,0,',',' ')." FCFA\n";
}
