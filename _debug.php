<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Use in-memory sqlite from phpunit? Instead inspect query builder SQL via reflection
$user = \App\Models\User::role(\App\Enums\RoleName::SUPER_ADMIN->value)->first();
if (!$user) { echo "no admin\n"; exit; }
\Illuminate\Support\Facades\Auth::login($user);
$q = \App\Filament\Resources\Donations\DonationResource::getEloquentQuery();
echo $q->toSql()."\n";
echo json_encode($q->getBindings())."\n";
