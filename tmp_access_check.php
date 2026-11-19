<?php

$access = app(App\Services\ChurchAccessService::class);

foreach ([2, 3, 4] as $id) {
    $user = App\Models\User::find($id);
    echo "UTILISATEUR {$id} {$user->email} role={$user->roleName()}".PHP_EOL;

    foreach (App\Models\Church::orderBy('id')->get() as $church) {
        echo '  '.$church->id.' '.$church->name.' ('.$church->type->value.') : '
            .($access->canAccessChurchData($user, $church->id) ? 'ACCES' : '-').PHP_EOL;
    }

    echo '  accessibles: '.implode(',', $access->accessibleChurchIds($user)).PHP_EOL;
}
