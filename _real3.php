<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;

$user = User::where('email', 'wwashington@gmail.com')->firstOrFail();
Auth::login($user);

$page = app(App\Filament\Pages\TopPartners::class);
$page->mount();
echo "data church_id: ".var_export($page->data['church_id'] ?? null, true)."\n";
$ranking = $page->getRanking();
echo "ranking church: ".var_export($ranking['church'] ?? null, true)."\n";
echo "items: ".count($ranking['items'] ?? [])."\n";

// Options du Select
$schema = $page->form(Filament\Schemas\Schema::make($page));
echo "options:\n";
print_r($schema->getComponent('church_id')?->getOptions());
