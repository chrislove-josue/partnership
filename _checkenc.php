<?php
require 'C:/Project/partnership/vendor/autoload.php';
$app = require 'C:/Project/partnership/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Church;
use App\Models\User;
use App\Models\CategoryDon;
echo "=== CHURCHES ===\n";
foreach (Church::all() as $c) echo "  ".$c->name." (".bin2hex($c->name).")\n";
echo "=== USERS (first 8) ===\n";
foreach (User::limit(8)->get() as $u) echo "  ".$u->name." | ".$u->email."\n";
echo "=== CATEGORIES ===\n";
foreach (CategoryDon::all() as $c) echo "  ".$c->name." (".bin2hex($c->name).")\n";
