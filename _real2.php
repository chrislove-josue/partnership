<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;

$user = User::where('email', 'wwashington@gmail.com')->firstOrFail();
Auth::login($user);

$controller = app(App\Http\Controllers\Api\TopPartnerController::class);

function show(string $label, App\Http\Controllers\Api\TopPartnerController $controller): void {
    $request = Illuminate\Http\Request::create('/api/top-partners?period=month&month=11&year=2026'.($GLOBALS['qs'] ?? ''), 'GET');
    $request->setUserResolver(fn () => Auth::user());
    $response = $controller->index($request);
    $data = json_decode($response->getContent(), true);
    echo "\n=== $label ===\n";
    echo 'church_id: '.var_export($data['church_id'] ?? null, true)."\n";
    echo 'count: '.$data['count']."\n";
    foreach ($data['data'] as $item) {
        echo "  {$item['name']} | église={$item['church']} | origine={$item['origin_church']} | {$item['amount']} FCFA\n";
    }
}

show('Sans filtre (défaut)', $controller);
$GLOBALS['qs'] = '&church_id=';
show('Toutes les églises accessibles', $controller);
$GLOBALS['qs'] = '&church_id=8';
show('CE COME 2 (id 8)', $controller);
$GLOBALS['qs'] = '&church_id=2';
show('CE CALAVI (id 2)', $controller);
