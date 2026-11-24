<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Hash;

$user = App\Models\User::where('email', 'wwashington@gmail.com')->firstOrFail();
echo "password ok: ".(Hash::check('azerty123', $user->password) ? 'YES' : 'NO')."\n";
echo "user: {$user->id} {$user->name} role={$user->roleName()} assigned=".($user->assigned_church_id ?? 'null')."\n";

$access = app(App\Services\ChurchAccessService::class);
echo "defaultChurchId: ".$access->defaultChurchId($user)."\n";
echo "accessible: ".implode(',', $access->accessibleChurchIds($user))."\n";
foreach (App\Models\Church::whereIn('id', $access->accessibleChurchIds($user))->orderBy('id')->get() as $c) {
    echo "  {$c->id} | {$c->name} | {$c->type?->value} | parent=".($c->parent_id ?? 'null')."\n";
}
