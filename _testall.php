<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;

$user = User::where('email', 'wwashington@gmail.com')->firstOrFail();
Auth::login($user);
$c = app(App\Http\Controllers\Api\TopPartnerController::class);

foreach (['' => 'toutes accessibles', 'all' => 'toutes la base', '8' => 'CE COME 2'] as $value => $label) {
    $req = Illuminate\Http\Request::create('/api/top-partners?period=month&month=11&year=2026&church_id='.$value, 'GET');
    $req->setUserResolver(fn () => Auth::user());
    $d = json_decode($c->index($req)->getContent(), true);
    echo "$label: count={$d['count']} rang1=".($d['data'][0]['name'] ?? '-').' '.($d['data'][0]['amount'] ?? 0)." FCFA\n";
}
