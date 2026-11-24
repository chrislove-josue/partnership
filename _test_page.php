<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Bootstrap auth
$user = \App\Models\User::where('email', 'sounesatchougo@gmail.com')->firstOrFail();
\Illuminate\Support\Facades\Auth::login($user);

try {
    $page = app(\App\Filament\Pages\Birthdays::class);
    echo "Page instantiated OK\n";
    echo "navLabel: ".$page->getNavigationLabel()."\n";
    echo "navGroup: ".($page->getNavigationGroup() ?? 'null')."\n";
} catch (\Throwable $e) {
    echo "ERROR: ".$e->getMessage()."\n";
    echo $e->getTraceAsString()."\n";
}
