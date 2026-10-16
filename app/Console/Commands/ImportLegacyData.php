<?php

namespace App\Console\Commands;

use App\Models\CategoryDon;
use App\Models\Cellule;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Notification;
use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Importe les données de l'ancienne base (export Prisma/Neon au format JSON)
 * vers la base MySQL de l'application Laravel.
 *
 * Usage: php artisan legacy:import [--path=storage/app/legacy-export.json] [--force]
 */
class ImportLegacyData extends Command
{
    protected $signature = 'legacy:import
        {--path= : Chemin du fichier JSON exporté (défaut: storage/app/legacy-export.json)}
        {--force : Ne pas demander de confirmation}';

    protected $description = 'Migre les données de l\'ancienne base (Postgres/Neon) vers la nouvelle base MySQL';

    /** @var array<string,int> */
    private array $churchMap = [];

    /** @var array<string,int> */
    private array $userMap = [];

    /** @var array<string,int> */
    private array $celluleMap = [];

    /** @var array<string,int> */
    private array $partnerMap = [];

    /** @var array<string,int> */
    private array $categoryMap = [];

    /** @var array<string,string> */
    private array $roleNames = [];

    public function handle(): int
    {
        $path = $this->option('path') ?: storage_path('app/legacy-export.json');

        if (! File::exists($path)) {
            $this->error("Fichier introuvable: {$path}");

            return self::FAILURE;
        }

        $data = json_decode(File::get($path), true);

        if (! is_array($data)) {
            $this->error('Fichier JSON invalide.');

            return self::FAILURE;
        }

        $this->info('Fichier: '.$path);
        foreach (['churches', 'users', 'cellules', 'partners', 'categoryDons', 'donations', 'notifications'] as $key) {
            $this->line('  · '.$key.': '.count($data[$key] ?? []));
        }

        if (! $this->option('force') && ! $this->confirm('Cette opération VIDE les données actuelles (églises, utilisateurs, partenaires, dons, notifications) puis importe l\'ancienne base. Continuer ?')) {
            $this->warn('Import annulé.');

            return self::SUCCESS;
        }

        $this->wipe();

        DB::transaction(function () use ($data) {
            $this->importCategories($data['categoryDons'] ?? []);
            $this->importChurches($data['churches'] ?? []);
            $this->importUsers($data['users'] ?? [], $data['roles'] ?? []);
            $this->linkChurchManagers($data['churches'] ?? []);
            $this->importCellules($data['cellules'] ?? []);
            $this->importPartners($data['partners'] ?? []);
            $this->linkCelluleLeaders($data['cellules'] ?? []);
            $this->importDonations($data['donations'] ?? []);
            $this->importNotifications($data['notifications'] ?? []);
        });

        // Garantir l'accès à l'administrateur courant (compte absent de l'ancienne base).
        $this->call('db:seed', ['--class' => UserSeeder::class, '--force' => true]);

        $this->newLine();
        $this->info('Import terminé.');
        $this->table(
            ['Table', 'Lignes'],
            [
                ['churches', Church::count()],
                ['users', User::count()],
                ['cellules', Cellule::count()],
                ['partners', Partner::count()],
                ['category_dons', CategoryDon::count()],
                ['donations', Donation::count()],
                ['notifications', Notification::count()],
            ],
        );

        return self::SUCCESS;
    }

    /**
     * Normalise un hachage bcrypt pour Laravel.
     *
     * Node (bcryptjs) génère des hachages en $2a$/$2b$ que le hasher de Laravel
     * refuse ("This password does not use the Bcrypt algorithm"). $2y$ est
     * fonctionnellement identique à $2b$, seul le préfixe diffère.
     */
    private function normalizeHash(?string $hash): string
    {
        if (empty($hash)) {
            return Hash::make('azerty123');
        }

        if (str_starts_with($hash, '$2a$') || str_starts_with($hash, '$2b$')) {
            return '$2y$'.substr($hash, 4);
        }

        return $hash;
    }

    private function wipe(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'notifications',
            'donations',
            'partners',
            'cellules',
            'churches',
            'users',
            'model_has_roles',
            'model_has_permissions',
            'magic_tokens',
        ] as $table) {
            DB::table($table)->truncate();
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     */
    private function importCategories(array $rows): void
    {
        foreach ($rows as $row) {
            $category = CategoryDon::updateOrCreate(
                ['name' => $row['name']],
                ['color' => $row['color'] ?? '#3B82F6'],
            );

            $this->categoryMap[$row['id']] = $category->id;
        }
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     */
    private function importChurches(array $rows): void
    {
        // 1re passe : toutes les églises sans parent ni responsable (FK).
        foreach ($rows as $row) {
            $id = Church::query()->insertGetId([
                'name' => $row['name'],
                'type' => $row['type'],
                'parent_id' => null,
                'manager_id' => null,
                'created_at' => Carbon::parse($row['createdAt']),
                'updated_at' => Carbon::parse($row['updatedAt']),
            ]);

            $this->churchMap[$row['id']] = $id;
        }

        // 2e passe : hiérarchie parent/enfant.
        foreach ($rows as $row) {
            if (! empty($row['parentId']) && isset($this->churchMap[$row['parentId']])) {
                Church::query()->where('id', $this->churchMap[$row['id']])->update([
                    'parent_id' => $this->churchMap[$row['parentId']],
                ]);
            }
        }
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @param  array<int,array<string,mixed>>  $roles
     */
    private function importUsers(array $rows, array $roles): void
    {
        foreach ($roles as $role) {
            $this->roleNames[$role['id']] = $role['name'];
        }

        $knownRoles = Role::pluck('name')->all();

        foreach ($rows as $row) {
            $id = User::query()->insertGetId([
                'name' => $row['name'] ?? $row['email'],
                'email' => $row['email'],
                'email_verified_at' => ! empty($row['emailVerified']) ? Carbon::parse($row['emailVerified']) : null,
                'password' => $this->normalizeHash($row['password'] ?? null),
                'remember_token' => null,
                'assigned_church_id' => $this->churchMap[$row['assignedChurchId']] ?? null,
                'is_active' => ! empty($row['isActive']) ? 1 : 0,
                'phone' => $row['phone'] ?? null,
                'handle_kc' => $row['handleKC'] ?? null,
                'created_at' => Carbon::parse($row['createdAt']),
                'updated_at' => Carbon::parse($row['updatedAt']),
            ]);

            $this->userMap[$row['id']] = $id;

            $roleName = $this->roleNames[$row['roleId']] ?? null;

            if ($roleName && in_array($roleName, $knownRoles, true)) {
                User::find($id)->syncRoles([$roleName]);
            } else {
                $this->warn("Rôle inconnu pour {$row['email']} (roleId: {$row['roleId']}).");
            }
        }
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     */
    private function linkChurchManagers(array $rows): void
    {
        foreach ($rows as $row) {
            if (! empty($row['managerId']) && isset($this->userMap[$row['managerId']])) {
                Church::query()->where('id', $this->churchMap[$row['id']])->update([
                    'manager_id' => $this->userMap[$row['managerId']],
                ]);
            }
        }
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     */
    private function importCellules(array $rows): void
    {
        foreach ($rows as $row) {
            $id = Cellule::query()->insertGetId([
                'name' => $row['name'],
                'church_id' => $this->churchMap[$row['churchId']],
                'leader_id' => null,
                'created_at' => Carbon::parse($row['createdAt']),
                'updated_at' => Carbon::parse($row['updatedAt']),
            ]);

            $this->celluleMap[$row['id']] = $id;
        }
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     */
    private function importPartners(array $rows): void
    {
        foreach ($rows as $row) {
            $id = Partner::query()->insertGetId([
                'first_name' => $row['firstName'],
                'last_name' => $row['lastName'],
                'email' => $row['email'] ?? null,
                'phone' => $row['phone'] ?? null,
                'handle_kc' => $row['handleKC'] ?? null,
                'address' => $row['address'] ?? null,
                'birthdate' => ! empty($row['birthdate']) ? Carbon::parse($row['birthdate'])->toDateString() : null,
                'cellule_id' => ! empty($row['celluleId']) ? ($this->celluleMap[$row['celluleId']] ?? null) : null,
                'church_id' => $this->churchMap[$row['churchId']],
                'created_at' => Carbon::parse($row['createdAt']),
                'updated_at' => Carbon::parse($row['updatedAt']),
            ]);

            $this->partnerMap[$row['id']] = $id;
        }
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     */
    private function linkCelluleLeaders(array $rows): void
    {
        foreach ($rows as $row) {
            if (! empty($row['leaderId']) && isset($this->partnerMap[$row['leaderId']])) {
                Cellule::query()->where('id', $this->celluleMap[$row['id']])->update([
                    'leader_id' => $this->partnerMap[$row['leaderId']],
                ]);
            }
        }
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     */
    private function importDonations(array $rows): void
    {
        $batch = [];

        foreach ($rows as $row) {
            if (! isset($this->partnerMap[$row['partnerId']], $this->churchMap[$row['churchId']], $this->categoryMap[$row['categoryId']])) {
                $this->warn("Don ignoré (référence manquante): {$row['id']}");

                continue;
            }

            $batch[] = [
                'amount' => $row['amount'],
                'date' => Carbon::parse($row['date']),
                'partner_id' => $this->partnerMap[$row['partnerId']],
                'church_id' => $this->churchMap[$row['churchId']],
                'category_id' => $this->categoryMap[$row['categoryId']],
                'created_at' => Carbon::parse($row['createdAt']),
                'updated_at' => Carbon::parse($row['updatedAt']),
            ];
        }

        foreach (array_chunk($batch, 500) as $chunk) {
            Donation::query()->insert($chunk);
        }
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     */
    private function importNotifications(array $rows): void
    {
        $batch = [];

        foreach ($rows as $row) {
            if (! isset($this->userMap[$row['userId']])) {
                continue;
            }

            $batch[] = [
                'id' => (string) Str::uuid(),
                'type' => $row['type'],
                'title' => $row['title'],
                'message' => $row['message'],
                'data' => ! empty($row['data']) ? json_encode($row['data'], JSON_UNESCAPED_UNICODE) : null,
                'is_read' => ! empty($row['isRead']) ? 1 : 0,
                'user_id' => $this->userMap[$row['userId']],
                'created_at' => Carbon::parse($row['createdAt']),
            ];
        }

        foreach (array_chunk($batch, 500) as $chunk) {
            Notification::query()->insert($chunk);
        }
    }
}
