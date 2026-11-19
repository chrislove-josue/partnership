<?php

$church = App\Models\Church::find(1);
$access = app(App\Services\ChurchAccessService::class);
$ids = $access->accessibleChurchIds(App\Models\User::find(2));

echo 'Sikecodji parent_id = '.var_export($church->parent_id, true).PHP_EOL;
echo 'Accessibles pour le pasteur de groupe = '.count($ids).' entrees, '.count(array_unique($ids)).' uniques'.PHP_EOL;
echo 'IDs uniques = '.implode(',', array_unique($ids)).PHP_EOL;
echo 'Migrations en attente = '.count(array_filter(
    App\Support\DonationCategoryNames::CANONICAL,
    fn (string $name) => App\Models\CategoryDon::where('name', $name)->doesntExist()
)).' categories françaises absentes'.PHP_EOL;
