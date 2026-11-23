<?php
require 'C:/Project/partnership/vendor/autoload.php';
$app = require 'C:/Project/partnership/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Partner;
use App\Models\Church;
use App\Models\Donation;
use App\Models\CategoryDon;
use App\Models\Cellule;
echo "Users: ".User::count()."\n";
echo "Partners: ".Partner::count()."\n";
echo "Churches: ".Church::count()."\n";
echo "Donations: ".Donation::count()."\n";
echo "Categories: ".CategoryDon::count()."\n";
echo "Cellules: ".Cellule::count()."\n";
