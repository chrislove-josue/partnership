<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (\App\Models\Church::whereIn('name', ['CE COME 2','CE CALAVI'])->get() as $c) {
    echo "CHURCH {$c->id} | {$c->name} | type={$c->type?->value} | parent=".($c->parent_id ?? 'null')."\n";
}
echo "---USERS WITH ASSIGNED---\n";
foreach (\App\Models\User::whereNotNull('assigned_church_id')->get() as $u) {
    $church = $u->assignedChurch;
    if ($church && in_array($church->name, ['CE COME 2','CE CALAVI'], true)) {
        $ids = app(\App\Services\ChurchAccessService::class)->accessibleChurchIds($u);
        $names = \App\Models\Church::whereIn('id', $ids)->pluck('name','id')->all();
        echo "USER {$u->id} {$u->email} | assigned={$church->name} ({$church->id}) | role={$u->roleName()}\n";
        echo "  accessible ids: ".implode(',', $ids)."\n";
        echo "  accessible names: ".implode(' | ', $names)."\n";
        echo "  default: ".app(\App\Services\ChurchAccessService::class)->defaultChurchId($u)."\n";
    }
}
