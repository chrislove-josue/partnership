<?php

use App\Models\Role;
use Spatie\Permission\Models\Permission;

try {
    $p = Permission::findByName('READ_OWN_CHURCH_DATA', 'web');
    echo 'FOUND:'.$p->id.PHP_EOL;
} catch (\Throwable $e) {
    echo 'ERR:'.$e->getMessage().PHP_EOL;
}

$r = Role::first();
echo 'ROLE:'.$r->name.' guard='.$r->guard_name.PHP_EOL;
echo 'defaultGuard='.\Spatie\Permission\Guard::getDefaultName($r).PHP_EOL;
