<?php

/**
 * Import des données de l'ancienne base PostgreSQL (projet Next.js / Prisma / Neon)
 * vers la base Laravel PostgreSQL courante.
 *
 * Usage : php database/import-nextjs.php
 * Idempotent : relancer ne duplique pas (mapping par clé naturelle).
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// ---------------------------------------------------------------
// 1. Connexion à l'ancienne base (Neon, projet Next.js)
// ---------------------------------------------------------------
$env = file_get_contents(__DIR__.'/../../sponsorship-traking/.env');
if (! preg_match('/^DATABASE_URL="([^"]+)"/m', $env, $m)) {
    fwrite(STDERR, "DATABASE_URL introuvable dans sponsorship-traking/.env\n");
    exit(1);
}
$p = parse_url($m[1]);
$old = new PDO(
    sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=require', $p['host'], $p['port'] ?? 5432, ltrim($p['path'], '/')),
    urldecode($p['user']),
    urldecode($p['pass']),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 30]
);

/** @var array<string,int> maps ancien id (cuid) => nouvel id */
$churchMap = [];
$userMap = [];
$catMap = [];
$celluleMap = [];
$partnerMap = [];
$stats = [];

DB::transaction(function () use ($old, &$churchMap, &$userMap, &$catMap, &$celluleMap, &$partnerMap, &$stats) {
    // -----------------------------------------------------------
    // 2. Roles & Permissions : déjà créés par RoleAndPermissionSeeder (mêmes noms)
    // -----------------------------------------------------------
    $roleMap = [];
    foreach ($old->query('SELECT id, name FROM "Role"') as $r) {
        $newId = DB::table('roles')->where('name', $r['name'])->value('id');
        if ($newId) {
            $roleMap[$r['id']] = $newId;
        } else {
            $newId = DB::table('roles')->insertGetId([
                'name' => $r['name'], 'guard_name' => 'web', 'is_system' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $roleMap[$r['id']] = $newId;
        }
    }
    $stats['roles'] = count($roleMap);

    // -----------------------------------------------------------
    // 3. Eglises : insertion sans FK (parent/manager = NULL), puis mise à jour
    // -----------------------------------------------------------
    foreach ($old->query('SELECT id, name, type, "parentId", "managerId", "createdAt", "updatedAt" FROM "Church"') as $c) {
        $existing = DB::table('churches')->where('name', $c['name'])->value('id');
        if ($existing) {
            $churchMap[$c['id']] = (int) $existing;
            $stats['churches_rencontrees'] = ($stats['churches_rencontrees'] ?? 0) + 1;

            continue;
        }
        $churchMap[$c['id']] = DB::table('churches')->insertGetId([
            'name' => $c['name'],
            'type' => $c['type'],
            'parent_id' => null,
            'manager_id' => null,
            'created_at' => $c['createdAt'],
            'updated_at' => $c['updatedAt'],
        ]);
        $stats['churches'] = ($stats['churches'] ?? 0) + 1;
    }

    // -----------------------------------------------------------
    // 4. Categories de dons : map par nom (seedées en cible)
    // -----------------------------------------------------------
    foreach ($old->query('SELECT id, name, color, "createdAt", "updatedAt" FROM "CategoryDon"') as $c) {
        $existing = DB::table('category_dons')->where('name', $c['name'])->value('id');
        if ($existing) {
            $catMap[$c['id']] = (int) $existing;

            continue;
        }
        $catMap[$c['id']] = DB::table('category_dons')->insertGetId([
            'name' => $c['name'],
            'color' => $c['color'] ?: '#3B82F6',
            'created_at' => $c['createdAt'],
            'updated_at' => $c['updatedAt'],
        ]);
        $stats['categories'] = ($stats['categories'] ?? 0) + 1;
    }

    // -----------------------------------------------------------
    // 5. Utilisateurs : mdp bcrypt $2b$ conservés (compatibles Laravel)
    // -----------------------------------------------------------
    foreach ($old->query('SELECT id, email, name, "roleId", "assignedChurchId", "isActive", phone, "handleKC", "emailVerified", password, "createdAt", "updatedAt" FROM "User"') as $u) {
        $existing = DB::table('users')->where('email', $u['email'])->value('id');
        $data = [
            'name' => $u['name'] ?: strstr($u['email'], '@', true),
            'email' => $u['email'],
            'email_verified_at' => $u['emailVerified'],
            'password' => $u['password'] ?: Hash::make(Str::random(32)),
            'assigned_church_id' => $churchMap[$u['assignedChurchId']] ?? null,
            'is_active' => (bool) $u['isActive'],
            'phone' => $u['phone'],
            'handle_kc' => $u['handleKC'],
            'created_at' => $u['createdAt'],
            'updated_at' => $u['updatedAt'],
        ];
        if ($existing) {
            DB::table('users')->where('id', $existing)->update($data);
            $userMap[$u['id']] = (int) $existing;
            $stats['users_rencontres'] = ($stats['users_rencontres'] ?? 0) + 1;
        } else {
            $userMap[$u['id']] = DB::table('users')->insertGetId($data + ['remember_token' => Str::random(10)]);
            $stats['users'] = ($stats['users'] ?? 0) + 1;
        }

        // role via spatie model_has_roles
        $roleId = $roleMap[$u['roleId']] ?? null;
        $newUserId = $userMap[$u['id']];
        if ($roleId) {
            $has = DB::table('model_has_roles')
                ->where('role_id', $roleId)
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $newUserId)
                ->exists();
            if (! $has) {
                DB::table('model_has_roles')->insert([
                    'role_id' => $roleId,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $newUserId,
                ]);
            }
        }
    }
    $stats['users_total'] = count($userMap);

    // -----------------------------------------------------------
    // 6. Mise à jour FK des eglises (parent, manager)
    // -----------------------------------------------------------
    foreach ($old->query('SELECT id, "parentId", "managerId" FROM "Church"') as $c) {
        $newId = $churchMap[$c['id']] ?? null;
        if (! $newId) {
            continue;
        }
        DB::table('churches')->where('id', $newId)->update([
            'parent_id' => $c['parentId'] ? ($churchMap[$c['parentId']] ?? null) : null,
            'manager_id' => $c['managerId'] ? ($userMap[$c['managerId']] ?? null) : null,
        ]);
    }

    // -----------------------------------------------------------
    // 7. Cellules (leader apres les partenaires)
    // -----------------------------------------------------------
    foreach ($old->query('SELECT id, name, "churchId", "leaderId", "createdAt", "updatedAt" FROM "Cellule"') as $c) {
        $newChurchId = $churchMap[$c['churchId']] ?? null;
        if (! $newChurchId) {
            continue;
        }
        $existing = DB::table('cellules')->where('name', $c['name'])->where('church_id', $newChurchId)->value('id');
        if ($existing) {
            $celluleMap[$c['id']] = (int) $existing;

            continue;
        }
        $celluleMap[$c['id']] = DB::table('cellules')->insertGetId([
            'name' => $c['name'],
            'church_id' => $newChurchId,
            'leader_id' => null,
            'created_at' => $c['createdAt'],
            'updated_at' => $c['updatedAt'],
        ]);
        $stats['cellules'] = ($stats['cellules'] ?? 0) + 1;
    }

    // -----------------------------------------------------------
    // 8. Partenaires
    // -----------------------------------------------------------
    foreach ($old->query('SELECT id, email, phone, "handleKC", "celluleId", "churchId", address, birthdate, "firstName", "lastName", "createdAt", "updatedAt" FROM "Partner"') as $pt) {
        $newChurchId = $churchMap[$pt['churchId']] ?? null;
        if (! $newChurchId) {
            continue;
        }
        $existing = DB::table('partners')->where('email', $pt['email'])->value('id');
        $data = [
            'first_name' => $pt['firstName'],
            'last_name' => $pt['lastName'],
            'email' => $pt['email'],
            'phone' => $pt['phone'],
            'handle_kc' => $pt['handleKC'],
            'address' => $pt['address'],
            'birthdate' => $pt['birthdate'] ? substr($pt['birthdate'], 0, 10) : null,
            'cellule_id' => $pt['celluleId'] ? ($celluleMap[$pt['celluleId']] ?? null) : null,
            'church_id' => $newChurchId,
            'created_at' => $pt['createdAt'],
            'updated_at' => $pt['updatedAt'],
        ];
        if ($existing) {
            DB::table('partners')->where('id', $existing)->update($data);
            $partnerMap[$pt['id']] = (int) $existing;
        } else {
            $partnerMap[$pt['id']] = DB::table('partners')->insertGetId($data);
            $stats['partenaires'] = ($stats['partenaires'] ?? 0) + 1;
        }
    }

    // Leaders de cellules (apres insertion des partenaires)
    foreach ($old->query('SELECT id, "leaderId" FROM "Cellule" WHERE "leaderId" IS NOT NULL') as $c) {
        $celluleId = $celluleMap[$c['id']] ?? null;
        $leaderId = $partnerMap[$c['leaderId']] ?? null;
        if ($celluleId && $leaderId) {
            DB::table('cellules')->where('id', $celluleId)->update(['leader_id' => $leaderId]);
        }
    }

    // -----------------------------------------------------------
    // 9. Dons
    // -----------------------------------------------------------
    foreach ($old->query('SELECT id, amount, date, "partnerId", "churchId", "categoryId", "createdAt", "updatedAt" FROM "Donation"') as $d) {
        $partnerId = $partnerMap[$d['partnerId']] ?? null;
        $churchId = $churchMap[$d['churchId']] ?? null;
        $catId = $catMap[$d['categoryId']] ?? null;
        if (! $partnerId || ! $churchId || ! $catId) {
            $stats['dons_sautés'] = ($stats['dons_sautés'] ?? 0) + 1;

            continue;
        }
        $exists = DB::table('donations')
            ->where('partner_id', $partnerId)
            ->where('amount', $d['amount'])
            ->where('date', substr($d['date'], 0, 19))
            ->exists();
        if ($exists) {
            continue;
        }
        DB::table('donations')->insert([
            'amount' => $d['amount'],
            'date' => substr($d['date'], 0, 19),
            'partner_id' => $partnerId,
            'church_id' => $churchId,
            'category_id' => $catId,
            'created_at' => $d['createdAt'],
            'updated_at' => $d['updatedAt'],
        ]);
        $stats['dons'] = ($stats['dons'] ?? 0) + 1;
    }

    // -----------------------------------------------------------
    // 10. Notifications (PK uuid en cible)
    // -----------------------------------------------------------
    foreach ($old->query('SELECT id, type, title, message, data, "isRead", "userId", "createdAt" FROM "Notification"') as $n) {
        $userId = $userMap[$n['userId']] ?? null;
        if (! $userId) {
            $stats['notifications_sautées'] = ($stats['notifications_sautées'] ?? 0) + 1;

            continue;
        }
        $exists = DB::table('notifications')
            ->where('user_id', $userId)
            ->where('title', $n['title'])
            ->where('created_at', substr($n['createdAt'], 0, 19))
            ->exists();
        if ($exists) {
            continue;
        }
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => $n['type'],
            'title' => $n['title'],
            'message' => $n['message'],
            'data' => $n['data'],
            'is_read' => (bool) $n['isRead'],
            'user_id' => $userId,
            'created_at' => substr($n['createdAt'], 0, 19),
        ]);
        $stats['notifications'] = ($stats['notifications'] ?? 0) + 1;
    }

    // -----------------------------------------------------------
    // 11. Magic tokens (liens de connexion)
    // -----------------------------------------------------------
    foreach ($old->query('SELECT token, email, "expiresAt", used, "createdAt" FROM "MagicToken"') as $t) {
        $exists = DB::table('magic_tokens')->where('token', $t['token'])->exists();
        if ($exists) {
            continue;
        }
        DB::table('magic_tokens')->insert([
            'token' => $t['token'],
            'email' => $t['email'],
            'expires_at' => substr($t['expiresAt'], 0, 19),
            'used' => (bool) $t['used'],
            'created_at' => $t['createdAt'],
        ]);
        $stats['magic_tokens'] = ($stats['magic_tokens'] ?? 0) + 1;
    }
});

// ---------------------------------------------------------------
// Rapport
// ---------------------------------------------------------------
echo "=== Import termine ===\n";
foreach ($stats as $k => $v) {
    echo str_pad($k, 28)." : $v\n";
}
echo "\nComptages cible :\n";
foreach (['users', 'churches', 'cellules', 'partners', 'donations', 'category_dons', 'notifications', 'magic_tokens', 'roles'] as $t) {
    echo str_pad($t, 20).' : '.DB::table($t)->count()."\n";
}
