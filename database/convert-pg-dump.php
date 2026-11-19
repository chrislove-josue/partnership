<?php

/**
 * Convertit un dump pg_dump (instructions INSERT) de l'ancienne application
 * Next.js/Prisma en JSON consommable par `php artisan legacy:import`.
 *
 * Usage : php database/convert-pg-dump.php <dump.sql> [--output=<fichier.json>]
 */
$arguments = array_slice($argv, 1);
$options = array_values(array_filter($arguments, fn (string $argument): bool => str_starts_with($argument, '--output=')));
$paths = array_values(array_filter($arguments, fn (string $argument): bool => ! str_starts_with($argument, '--')));

if (count($paths) !== 1) {
    fwrite(STDERR, "Usage : php database/convert-pg-dump.php <dump.sql> [--output=<fichier.json>]\n");
    exit(1);
}

$dumpPath = $paths[0];
$outputPath = $options ? substr($options[0], strlen('--output=')) : __DIR__.'/../storage/app/legacy-export.json';

if (! is_file($dumpPath) || ! is_readable($dumpPath)) {
    fwrite(STDERR, "Dump illisible : {$dumpPath}\n");
    exit(1);
}

$sql = file_get_contents($dumpPath);

if ($sql === false) {
    fwrite(STDERR, "Impossible de lire le dump : {$dumpPath}\n");
    exit(1);
}

/**
 * Découpe une liste de valeurs SQL en respectant les chaînes et les structures imbriquées.
 *
 * @return list<string>
 */
function splitSqlValues(string $values): array
{
    $parts = [];
    $current = '';
    $inString = false;
    $depth = 0;
    $length = strlen($values);

    for ($i = 0; $i < $length; $i++) {
        $character = $values[$i];

        if ($inString) {
            if ($character === "'" && ($values[$i + 1] ?? '') === "'") {
                $current .= "''";
                $i++;

                continue;
            }

            if ($character === "'") {
                $inString = false;
            }

            $current .= $character;

            continue;
        }

        if ($character === "'") {
            $inString = true;
            $current .= $character;

            continue;
        }

        if ($character === ',' && $depth === 0) {
            $parts[] = trim($current);
            $current = '';

            continue;
        }

        if (in_array($character, ['(', '[', '{'], true)) {
            $depth++;
        } elseif (in_array($character, [')', ']', '}'], true)) {
            $depth--;
        }

        $current .= $character;
    }

    $parts[] = trim($current);

    return $parts;
}

/** Convertit un littéral SQL en valeur PHP. */
function castSqlValue(string $value): mixed
{
    if ($value === 'NULL') {
        return null;
    }

    if ($value === 'true') {
        return true;
    }

    if ($value === 'false') {
        return false;
    }

    if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
        $text = str_replace("''", "'", substr($value, 1, -1));
        $decoded = json_decode($text, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $text;
    }

    if (is_numeric($value)) {
        return $value + 0;
    }

    return $value;
}

/**
 * Extrait les lignes INSERT du dump, indexées par table puis par ordre d'apparition.
 *
 * @return array<string, list<array<string, mixed>>>
 */
function parseDump(string $sql): array
{
    $pattern = '/^INSERT INTO (?:public\.)?"?([A-Za-z_]\w*)"?\s*\(([^)]*)\)\s*VALUES\s*\((.*)\);\s*$/m';

    if (preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER) === false) {
        throw new RuntimeException('Analyse du dump impossible (regex invalide).');
    }

    $tables = [];

    foreach ($matches as $match) {
        $columns = array_map(
            fn (string $column): string => trim($column, " \t\r\n\""),
            explode(',', $match[2])
        );
        $values = splitSqlValues($match[3]);

        if (count($columns) !== count($values)) {
            throw new RuntimeException("Ligne INSERT invalide pour {$match[1]} : ".count($columns).' colonnes, '.count($values).' valeurs.');
        }

        $tables[$match[1]][] = array_combine($columns, array_map('castSqlValue', $values));
    }

    return $tables;
}

try {
    $tables = parseDump($sql);
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}

$roles = $tables['Role'] ?? [];
$permissions = $tables['Permission'] ?? [];
$rolePermissionsById = [];

foreach ($tables['_PermissionToRole'] ?? [] as $link) {
    $rolePermissionsById[$link['B']][] = $permissions[array_search($link['A'], array_column($permissions, 'id'), true)] ?? null;
}

foreach ($roles as &$role) {
    $role['permissions'] = array_values(array_filter($rolePermissionsById[$role['id']] ?? []));
}
unset($role);

$export = [
    'churches' => $tables['Church'] ?? [],
    'users' => $tables['User'] ?? [],
    'roles' => $roles,
    'permissions' => $permissions,
    'cellules' => $tables['Cellule'] ?? [],
    'partners' => $tables['Partner'] ?? [],
    'categoryDons' => $tables['CategoryDon'] ?? [],
    'donations' => $tables['Donation'] ?? [],
    'notifications' => $tables['Notification'] ?? [],
    'magicTokens' => $tables['MagicToken'] ?? [],
    'accounts' => $tables['Account'] ?? [],
    'rolePermissions' => $roles,
];

$directory = dirname($outputPath);

if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
    fwrite(STDERR, "Impossible de créer le dossier : {$directory}\n");
    exit(1);
}

if (is_file($outputPath) && ! copy($outputPath, $outputPath.'.bak')) {
    fwrite(STDERR, "Impossible de sauvegarder l'export existant : {$outputPath}.bak\n");
    exit(1);
}

$json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

if (file_put_contents($outputPath, $json."\n") === false) {
    fwrite(STDERR, "Impossible d'écrire : {$outputPath}\n");
    exit(1);
}

echo "Export généré : {$outputPath}\n";

foreach ($export as $key => $rows) {
    echo str_pad($key, 20).' : '.count($rows)."\n";
}
