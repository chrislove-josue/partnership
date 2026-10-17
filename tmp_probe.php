<?php

// Sonde l'ancienne base PostgreSQL (Projet Next.js / Neon)
$env = file_get_contents(__DIR__ . '/../sponsorship-traking/.env');
preg_match('/^DATABASE_URL="([^"]+)"/m', $env, $m);
$url = $m[1];

$parts = parse_url($url);
$host = $parts['host'];
$port = $parts['port'] ?? 5432;
$db = ltrim($parts['path'], '/');
$user = urldecode($parts['user']);
$pass = urldecode($parts['pass']);

$pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db;sslmode=require", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_TIMEOUT => 30,
]);

$tables = ['Church', 'User', 'Role', 'Permission', 'Cellule', 'Partner', 'Donation', 'CategoryDon', 'Notification', 'Account', 'Session', 'MagicToken', 'RoleToPermission'];
echo "=== Comptages ===\n";
foreach ($tables as $t) {
    try {
        $c = $pdo->query('SELECT COUNT(*) FROM "' . $t . '"')->fetchColumn();
        echo "$t: $c\n";
    } catch (PDOException $e) {
        // table pivot possible
        echo "$t: (absente)\n";
    }
}

echo "\n=== Roles ===\n";
try {
    foreach ($pdo->query('SELECT id, name, "isSystem" FROM "Role" ORDER BY name') as $r) {
        echo "{$r['id']} | {$r['name']} | system={$r['isSystem']}\n";
    }
} catch (PDOException $e) {
    echo $e->getMessage() . "\n";
}

echo "\n=== Permissions ===\n";
try {
    foreach ($pdo->query('SELECT action FROM "Permission" ORDER BY action') as $p) {
        echo $p['action'] . "\n";
    }
} catch (PDOException $e) {
    echo $e->getMessage() . "\n";
}

echo "\n=== Role -> Permissions (table pivot) ===\n";
try {
    $n = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_name IN ('PermissionToRole','RoleToPermission','_RoleToPermission')")->fetchColumn();
    foreach (['PermissionToRole', 'RoleToPermission'] as $pivot) {
        try {
            $c = $pdo->query('SELECT COUNT(*) FROM "' . $pivot . '"')->fetchColumn();
            echo "$pivot: $c\n";
        } catch (PDOException $e) {}
    }
} catch (PDOException $e) {}

echo "\n=== Eglises ===\n";
foreach ($pdo->query('SELECT id, name, type, "parentId" FROM "Church" ORDER BY name') as $c) {
    echo "{$c['id']} | {$c['name']} | {$c['type']} | parent={$c['parentId']}\n";
}

echo "\n=== Utilisateurs ===\n";
foreach ($pdo->query('SELECT u.id, u.email, u.name, r.name AS role, u."assignedChurchId", u."isActive" FROM "User" u LEFT JOIN "Role" r ON r.id = u."roleId" ORDER BY u.email') as $u) {
    echo "{$u['id']} | {$u['email']} | {$u['name']} | role={$u['role']} | church={$u['assignedChurchId']} | active={$u['isActive']}\n";
}

echo "\n=== Categories ===\n";
foreach ($pdo->query('SELECT id, name, color FROM "CategoryDon" ORDER BY name') as $c) {
    echo "{$c['id']} | {$c['name']} | {$c['color']}\n";
}
echo "\n=== Toutes les tables ===\n";
$tableList = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
foreach ($tableList as $r) {
    echo $r['table_name'] . "\n";
}

echo "\n=== Pivot _PermissionToRole ===\n";
foreach ($pdo->query('SELECT r.name AS role, p.action FROM "_PermissionToRole" pr JOIN "Role" r ON r.id = pr."A" JOIN "Permission" p ON p.id = pr."B" ORDER BY r.name, p.action') as $row) {
    echo $row['role'] . ' => ' . $row['action'] . "\n";
}

echo "\n=== Cellules ===\n";
foreach ($pdo->query('SELECT c.id, c.name, c."churchId", c."leaderId", ch.name AS church FROM "Cellule" c JOIN "Church" ch ON ch.id = c."churchId"') as $row) {
    echo "{$row['id']} | {$row['name']} | church={$row['church']} | leader={$row['leaderId']}\n";
}

echo "\n=== Partners ===\n";
foreach ($pdo->query('SELECT p.id, p."firstName", p."lastName", p.email, p.phone, p."handleKC", p."celluleId", p."churchId", p.birthdate FROM "Partner" p') as $row) {
    echo "{$row['id']} | {$row['firstName']} {$row['lastName']} | {$row['email']} | {$row['phone']} | {$row['handleKC']} | cellule={$row['celluleId']} | birth=" . ($row['birthdate'] ?? 'NULL') . "\n";
}

echo "\n=== Donations (apercu + totaux) ===\n";
$row = $pdo->query('SELECT COUNT(*) AS n, SUM(amount) AS total FROM "Donation"')->fetch();
echo "count={$row['n']} total={$row['total']}\n";
foreach ($pdo->query('SELECT d.id, d.amount, d.date, d."partnerId", d."churchId", d."categoryId" FROM "Donation" d ORDER BY d.date LIMIT 5') as $d) {
    echo "{$d['id']} | {$d['amount']} | {$d['date']} | partner={$d['partnerId']} | church={$d['churchId']} | cat={$d['categoryId']}\n";
}

echo "\n=== Users (mots de passe) ===\n";
foreach ($pdo->query('SELECT email, LEFT(password, 20) AS pw, "emailVerified" FROM "User"') as $u) {
    echo "{$u['email']} | pw={$u['pw']} | verified=" . ($u['emailVerified'] ?? 'NULL') . "\n";
}

echo "\n=== Notifications / MagicTokens ===\n";
$n = $pdo->query('SELECT type, COUNT(*) AS c FROM "Notification" GROUP BY type')->fetchAll(PDO::FETCH_KEY_PAIR);
print_r($n);
$m = $pdo->query('SELECT id, email, token, "expiresAt", used FROM "MagicToken"')->fetchAll(PDO::FETCH_ASSOC);
print_r($m);
