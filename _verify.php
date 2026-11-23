<?php
require 'C:/Project/partnership/vendor/autoload.php';
$app = require 'C:/Project/partnership/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\CategoryDon; use App\Models\User; use App\Models\Partner;
echo "=== CATEGORIES ===\n";
foreach (CategoryDon::all() as $c) echo "  ".$c->name." -> ".$c->group?->value."\n";
echo "=== USERS (sample) ===\n";
foreach (User::limit(3)->get() as $u) echo "  ".$u->name."\n";
echo "=== PARTNERS (sample) ===\n";
foreach (Partner::limit(3)->get() as $p) echo "  ".$p->first_name." ".$p->last_name."\n";
echo "Mirror partners: ".Partner::whereNotNull('user_id')->count()."\n";
