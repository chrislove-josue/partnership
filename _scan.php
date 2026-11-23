<?php
require 'C:/Project/partnership/vendor/autoload.php';
$app = require 'C:/Project/partnership/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$tables = ['users', 'churches', 'partners', 'cellules', 'category_dons', 'donations', 'notifications', 'magic_tokens'];
foreach ($tables as $table) {
    $cols = DB::select("SHOW COLUMNS FROM `$table`");
    $strCols = [];
    foreach ($cols as $col) {
        if (preg_match('/char|text/i', $col->Type)) $strCols[] = $col->Field;
    }
    if (empty($strCols)) continue;
    $rows = DB::table($table)->get();
    $found = false;
    foreach ($rows as $row) {
        foreach ($strCols as $col) {
            $val = $row->$col;
            if (is_string($val) && strpos($val, '?') !== false) {
                if (! $found) { echo "=== $table ===\n"; $found = true; }
                echo "  id={$row->id} $col: ".json_encode($val, JSON_UNESCAPED_UNICODE)."\n";
            }
        }
    }
}
