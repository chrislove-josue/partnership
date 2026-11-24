<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;

$u = User::where('email', 'wwashington@gmail.com')->firstOrFail();
Auth::login($u);
$req = Illuminate\Http\Request::create('/api/me', 'GET');
$req->setUserResolver(fn () => Auth::user());
$me = app(App\Http\Controllers\Api\AuthController::class)->me($req);
$d = json_decode($me->getContent(), true);
echo "role: ".var_export($d['user']['role'] ?? null, true)."\n";
echo "roles: ".json_encode($d['user']['roles'] ?? null)."\n";
echo "assigned: ".var_export($d['user']['assigned_church_id'] ?? null, true)."\n";
