<?php
require 'C:/Project/partnership/vendor/autoload.php';
$app = require 'C:/Project/partnership/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Partner;
use App\Services\UserPartnerSync;

$sync = app(UserPartnerSync::class);
$missing = 0;
foreach (User::all() as $user) {
    if (! $user->partner) {
        echo "MISSING partner for: {$user->email} (church: {$user->assigned_church_id})\n";
        $sync->sync($user);
        $missing++;
    }
}
echo "Checked ".User::count()." users. Created $missing missing mirror partners.\n";
echo "Total mirror partners: ".Partner::whereNotNull('user_id')->count()."\n";
echo "Total partners: ".Partner::count()."\n";
