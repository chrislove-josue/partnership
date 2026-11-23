<?php
require 'C:/Project/partnership/vendor/autoload.php';
$app = require 'C:/Project/partnership/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$replacements = [
    'Josu????' => 'Josué',
    'Fr????re' => 'Frère',
    'D????mes' => 'Dîmes',
    'R????alit????s' => 'Réalités',
    '?????cole' => 'École',
    'Gu????risons' => 'Guérisons',
];

// Fix users.name
foreach (DB::table('users')->get() as $row) {
    $name = $row->name;
    foreach ($replacements as $bad => $good) $name = str_replace($bad, $good, $name);
    if ($name !== $row->name) {
        DB::table('users')->where('id', $row->id)->update(['name' => $name]);
        echo "Fixed user {$row->id}: {$row->name} -> $name\n";
    }
}

// Fix partners.first_name (and last_name just in case)
foreach (DB::table('partners')->get() as $row) {
    $fn = $row->first_name;
    $ln = $row->last_name;
    foreach ($replacements as $bad => $good) { $fn = str_replace($bad, $good, $fn); $ln = str_replace($bad, $good, $ln); }
    if ($fn !== $row->first_name || $ln !== $row->last_name) {
        DB::table('partners')->where('id', $row->id)->update(['first_name' => $fn, 'last_name' => $ln]);
        echo "Fixed partner {$row->id}: {$row->first_name} -> $fn\n";
    }
}

// Fix category_dons.name
foreach (DB::table('category_dons')->get() as $row) {
    $name = $row->name;
    foreach ($replacements as $bad => $good) $name = str_replace($bad, $good, $name);
    if ($name !== $row->name) {
        DB::table('category_dons')->where('id', $row->id)->update(['name' => $name]);
        echo "Fixed category {$row->id}: {$row->name} -> $name\n";
    }
}

echo "Done.\n";
