<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Partner;
use App\Models\User;

echo 'Utilisateurs: '.User::count().PHP_EOL;
echo 'Utilisateurs avec église: '.User::whereNotNull('assigned_church_id')->count().PHP_EOL;
echo 'Partenaires miroirs: '.Partner::whereNotNull('user_id')->count().PHP_EOL;
echo 'Partenaires simples: '.Partner::whereNull('user_id')->count().PHP_EOL;

foreach (User::with('partner')->limit(10)->get() as $user) {
    echo sprintf(
        '- %s <%s> église=%s partenaire=%s (%s %s / %s)',
        $user->name,
        $user->email,
        $user->assigned_church_id ?? '—',
        $user->partner?->id ?? 'AUCUN',
        $user->partner?->first_name ?? '',
        $user->partner?->last_name ?? '',
        $user->partner?->church_id ?? '',
    ).PHP_EOL;
}
