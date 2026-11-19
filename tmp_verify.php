<?php

echo 'utilisateurs='.App\Models\User::count().PHP_EOL;
echo 'eglises='.App\Models\Church::count().PHP_EOL;

App\Models\User::where('email', 'sounesatchougo@gmail.com')->get()->each(function ($u): void {
    echo 'compte intact='.$u->email.'|'.implode(',', $u->getRoleNames()->all()).'|church='.($u->assigned_church_id ?? 'null').PHP_EOL;
});

$duplicates = App\Models\User::select('email')->groupBy('email')->havingRaw('COUNT(*) > 1')->pluck('email')->all();
echo 'emails en double='.(count($duplicates) ? implode(',', $duplicates) : 'aucun').PHP_EOL;
