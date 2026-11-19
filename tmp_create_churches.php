<?php

use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Models\Church;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$leaders = [
    [
        'church' => 'CE SIKECODJI',
        'type' => ChurchType::GROUP,
        'name' => 'Pasteur Nicolas Yao Adjaka',
        'email' => 'yaoadjaka@yahoo.fr',
        'role' => RoleName::GROUP_PASTOR,
    ],
    [
        'church' => 'CE CALAVI',
        'type' => ChurchType::SUB_GROUP,
        'name' => 'Pasteur Wisdom Washington',
        'email' => 'wwashington@gmail.com',
        'role' => RoleName::SUBGROUP_PASTOR,
    ],
    [
        'church' => 'CE FIDJROSSE',
        'type' => ChurchType::LOCAL,
        'name' => 'Pasteur Boris Aihin',
        'email' => 'baihin@gmail.com',
        'role' => RoleName::LOCAL_PASTOR,
    ],
    [
        'church' => 'CE PARAKOU',
        'type' => ChurchType::LOCAL,
        'name' => 'Diacre Caleb Dossa',
        'email' => 'cdossa@gmail.com',
        'role' => RoleName::LOCAL_PASTOR,
    ],
    [
        'church' => 'CE BOHICON',
        'type' => ChurchType::LOCAL,
        'name' => 'Pasteur Ives Gogan',
        'email' => 'igogan@gmail.com',
        'role' => RoleName::LOCAL_PASTOR,
    ],
    [
        'church' => 'CE HEVIE',
        'type' => ChurchType::LOCAL,
        'name' => 'Frère Sebastian Monkoun',
        'email' => 'smonkoun@gmail.com',
        'role' => RoleName::LOCAL_PASTOR,
    ],
    [
        'church' => 'CE PAHOU',
        'type' => ChurchType::LOCAL,
        'name' => 'Frère Franck Gbemenou',
        'email' => 'fgbemenou@gmail.com',
        'role' => RoleName::LOCAL_PASTOR,
    ],
    [
        'church' => 'CE COME 2',
        'type' => ChurchType::LOCAL,
        'name' => 'Frère Roger Kouton',
        'email' => 'rkouton@gmail.com',
        'role' => RoleName::LOCAL_PASTOR,
    ],
    [
        'church' => 'CE SEME',
        'type' => ChurchType::LOCAL,
        'name' => 'Frère Felix Gnadjihounde',
        'email' => 'fgnadjihounde@gmail.com',
        'role' => RoleName::LOCAL_PASTOR,
    ],
    [
        'church' => 'CE LOKOSSA',
        'type' => ChurchType::LOCAL,
        'name' => 'Frère Sebastian Aguidi',
        'email' => 'saguidi@gmail.com',
        'role' => RoleName::LOCAL_PASTOR,
    ],
    [
        'church' => 'CE POBE',
        'type' => ChurchType::LOCAL,
        'name' => 'Frère Albert Alovokpinhoun',
        'email' => 'aalovokpinhoun@gmail.com',
        'role' => RoleName::LOCAL_PASTOR,
    ],
];

DB::transaction(function () use ($leaders): void {
    foreach ($leaders as $leader) {
        $church = Church::whereRaw('LOWER(name) = ?', [mb_strtolower($leader['church'])])->first();

        if (! $church) {
            $church = Church::create([
                'name' => $leader['church'],
                'type' => $leader['type'],
                'parent_id' => null,
            ]);
            $churchAction = 'créée';
        } else {
            $church->update([
                'type' => $leader['type'],
                'parent_id' => null,
            ]);
            $churchAction = 'mise à jour';
        }

        $existingUser = User::whereRaw('LOWER(email) = ?', [mb_strtolower($leader['email'])])->first();

        $userData = [
            'name' => $leader['name'],
            'is_active' => true,
            'assigned_church_id' => $church->id,
        ];

        if ($existingUser) {
            $existingUser->update($userData);
            $user = $existingUser;
            $userAction = 'mis à jour';
        } else {
            $user = User::create(array_merge(['email' => $leader['email']], $userData, [
                'password' => Hash::make('azerty123'),
            ]));
            $userAction = 'créé';
        }

        $user->syncRoles([$leader['role']->value]);
        $user->syncImpliedRoles();

        $church->update(['manager_id' => $user->id]);

        echo sprintf(
            "%s : église %s (%s), compte %s (%s), rôles : %s\n",
            $leader['church'],
            $churchAction,
            $leader['type']->value,
            $leader['email'],
            $userAction,
            implode(', ', $user->fresh()->getRoleNames()->all()),
        );
    }
});

echo "\n--- ÉTAT FINAL ---\n";

Church::with(['manager', 'parent'])->orderBy('id')->get()->each(function (Church $church): void {
    echo implode(' | ', [
        $church->name,
        $church->type->value,
        'parent='.($church->parent ? $church->parent->name : 'null'),
        'responsable='.($church->manager ? $church->manager->name.' <'.$church->manager->email.'>' : 'null'),
    ]).PHP_EOL;
});
