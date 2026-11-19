<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== ÉGLISES ===\n";
foreach (App\Models\Church::with('manager')->orderBy('id')->get() as $church) {
    $manager = $church->manager
        ? $church->manager->name.' <'.$church->manager->email.'>'
        : '-';

    echo sprintf(
        "%d | %s | %s | parent=%s | manager=%s\n",
        $church->id,
        $church->name,
        $church->type->value,
        $church->parent_id ?? '-',
        $manager,
    );
}

echo "\n=== UTILISATEURS ===\n";
foreach (App\Models\User::with('roles')->orderBy('id')->get() as $user) {
    $roles = $user->roles->pluck('name')->implode(', ');

    echo sprintf(
        "%d | %s | %s | active=%s | church=%s | roles=%s\n",
        $user->id,
        $user->name,
        $user->email,
        $user->is_active ? '1' : '0',
        $user->assigned_church_id ?? '-',
        $roles ?: '-',
    );
}
