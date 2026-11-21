<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = App\Models\User::query()->with('roles')->get()->map(function ($user) {
    return [
        'id' => $user->id,
        'email' => $user->email,
        'roles' => $user->getRoleNames()->values()->all(),
        'azerty123' => Illuminate\Support\Facades\Hash::check('azerty123', $user->password),
    ];
})->take(40)->all();

dump($rows);
