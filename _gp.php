<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach (App\Models\User::role(App\Enums\RoleName::GROUP_PASTOR->value)->get() as $u) {
    echo $u->email.' | '.($u->assigned_church_id ?? 'null')."\n";
}
