<?php
require 'C:/Project/partnership/vendor/autoload.php';
$app = require 'C:/Project/partnership/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\User; use App\Models\Partner; use App\Models\Church; use App\Models\Donation; use App\Models\CategoryDon; use App\Models\Cellule;
echo "Users: ".User::count()."\nPartners: ".Partner::count()."\nChurches: ".Church::count()."\nDonations: ".Donation::count()."\nCategories: ".CategoryDon::count()."\nCellules: ".Cellule::count()."\n";
