<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Church;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\DB;

echo 'Total dons : ' . DB::table('donations')->sum('amount') . "\n\n";

echo "--- Eglises (total dons directs) ---\n";
foreach (Church::withTotalDonations()->orderByDesc('total_donations')->get() as $c) {
    echo str_pad($c->name, 22) . ' : ' . $c->total_donations . "\n";
}

echo "\n--- Cellules (total dons) ---\n";
foreach (\App\Models\Cellule::withTotalDonations()->get() as $c) {
    echo str_pad($c->name, 22) . ' : ' . $c->total_donations . ' | leader=' . ($c->leader?->full_name ?? '—') . "\n";
}

echo "\n--- Partenaires ---\n";
foreach (Partner::with('church')->get() as $p) {
    echo str_pad($p->full_name, 24) . ' | ' . str_pad($p->email, 30) . ' | ' . $p->church?->name . "\n";
}

echo "\n--- Utilisateurs (roles spatie) ---\n";
foreach (User::with('roles')->get() as $u) {
    echo str_pad($u->email, 34) . ' : ' . $u->getRoleNames()->implode(',') . ' | active=' . var_export($u->is_active, true) . "\n";
}
