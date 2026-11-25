<?php

namespace App\Console\Commands;

use App\Enums\ChurchType;
use App\Enums\RoleName;
use App\Models\Church;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Restructure l'arbre des églises selon l'organisation voulue :
 *
 *   Bénin Groupe 1 (GROUP)                 ← pasteur de groupe (Nicolas)
 *   ├── Sous-groupe 1 (SUB_GROUP)
 *   │   └── CE SIKECODJI (LOCAL, église mère) + ses filles
 *   └── Sous-groupe 2 (SUB_GROUP)
 *       └── CE CALAVI (LOCAL, église mère) + ses filles
 *
 * La commande est idempotente et crée une sauvegarde SQL avant toute écriture.
 */
class RestructureChurchHierarchy extends Command
{
    protected $signature = 'church:restructure {--force : Exécuter sans confirmation}';

    protected $description = 'Restructure les églises en Bénin Groupe 1 → Sous-groupes 1/2 → églises mères et filles';

    public function handle(): int
    {
        $this->info('=== Restructuration de la hiérarchie des églises ===');
        $this->line('');

        $rootName = 'Bénin Groupe 1';
        $sg1Name = 'Sous-groupe 1';
        $sg2Name = 'Sous-groupe 2';
        $mother1Name = 'CE SIKECODJI';
        $mother2Name = 'CE CALAVI';

        $mother1 = Church::where('name', $mother1Name)->first();
        $mother2 = Church::where('name', $mother2Name)->first();

        $this->line('Structure cible :');
        $this->line("  {$rootName} (GROUP)");
        $this->line("  ├── {$sg1Name} (SUB_GROUP) → {$mother1Name} (LOCAL) + ses filles");
        $this->line("  └── {$sg2Name} (SUB_GROUP) → {$mother2Name} (LOCAL) + ses filles");
        $this->line('');

        // Sauvegarde préalable de toutes les tables métier.
        if (! $this->backup()) {
            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Appliquer cette restructuration ?', false)) {
            $this->warn('Annulé.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($rootName, $sg1Name, $sg2Name, $mother1Name, $mother2Name): void {
                $root = Church::firstOrCreate(
                    ['name' => $rootName],
                    ['type' => ChurchType::GROUP, 'parent_id' => null, 'is_active' => true],
                );
                $root->update(['type' => ChurchType::GROUP, 'parent_id' => null]);

                $sg1 = Church::firstOrCreate(
                    ['name' => $sg1Name],
                    ['type' => ChurchType::SUB_GROUP, 'parent_id' => $root->id, 'is_active' => true],
                );
                $sg2 = Church::firstOrCreate(
                    ['name' => $sg2Name],
                    ['type' => ChurchType::SUB_GROUP, 'parent_id' => $root->id, 'is_active' => true],
                );

                foreach ([$sg1, $sg2] as $sg) {
                    $sg->update(['type' => ChurchType::SUB_GROUP, 'parent_id' => $root->id]);
                }

                // Les églises mères deviennent LOCAL sous leur sous-groupe.
                $mother1 = $mother1 ?? Church::create(['name' => $mother1Name, 'type' => ChurchType::LOCAL, 'is_active' => true]);
                $mother2 = $mother2 ?? Church::create(['name' => $mother2Name, 'type' => ChurchType::LOCAL, 'is_active' => true]);

                $mother1->update(['type' => ChurchType::LOCAL, 'parent_id' => $sg1->id]);
                $mother2->update(['type' => ChurchType::LOCAL, 'parent_id' => $sg2->id]);

                // Les filles restent rattachées à leur église mère (déjà LOCAL).
                Church::query()
                    ->whereNotIn('id', [$root->id, $sg1->id, $sg2->id, $mother1->id, $mother2->id])
                    ->update(['type' => ChurchType::LOCAL]);

                // Pasteur de groupe (Nicolas) assigné à la racine.
                $groupPastor = User::query()
                    ->where('email', 'yaoadjaka@yahoo.fr')
                    ->first();

                if ($groupPastor) {
                    $groupPastor->update(['assigned_church_id' => $root->id]);
                    $groupPastor->syncRoles([RoleName::GROUP_PASTOR->value]);
                    $groupPastor->syncImpliedRoles();
                }

                // Pasteur de sous-groupe de CE CALAVI assigné au Sous-groupe 2.
                $subgroupPastor = User::query()
                    ->where('email', 'wwashington@gmail.com')
                    ->first();

                if ($subgroupPastor) {
                    $subgroupPastor->update(['assigned_church_id' => $sg2->id]);
                    $subgroupPastor->syncRoles([RoleName::SUBGROUP_PASTOR->value]);
                    $subgroupPastor->syncImpliedRoles();
                }

                // Le responsable de l'église mère Sous-groupe 1 est le pasteur
                // local existant de CE SIKECODJI (inchangé).
            });
        } catch (Throwable $exception) {
            $this->error('Échec : '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Restructuration terminée.');
        $this->line('');
        $this->line('Arbre résultant :');

        foreach (Church::query()->orderBy('id')->get() as $church) {
            $depth = count($church->ancestorIds());
            $this->line(str_repeat('  ', $depth).'└ '.$church->id.' | '.$church->name.' | '.$church->type->value.' | parent='.($church->parent_id ?? 'null'));
        }

        $this->warn('N’oubliez pas de vider le cache : php artisan optimize:clear');

        return self::SUCCESS;
    }

    private function backup(): bool
    {
        $dir = storage_path('app/backups');

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $file = $dir.'/hierarchy-'.now()->format('Ymd-His').'.sql';
        $tables = ['churches', 'users', 'model_has_roles', 'donations', 'partners', 'cellules'];
        $sql = '-- Sauvegarde avant restructuration '.now()->toIso8601String()."\n\n";

        foreach ($tables as $table) {
            $rows = DB::table($table)->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $columns = array_keys((array) $rows->first());
            $sql .= 'INSERT INTO `'.$table.'` (`'.implode('`, `', $columns).'`) VALUES '."\n";

            $values = [];
            foreach ($rows as $row) {
                $cells = [];
                foreach ($columns as $column) {
                    $value = $row->{$column};
                    $cells[] = $value === null ? 'NULL' : DB::getPdo()->quote((string) $value);
                }
                $values[] = '('.implode(', ', $cells).')';
            }

            $sql .= implode(",\n", $values).";\n\n";
        }

        File::put($file, $sql);
        $this->info('Sauvegarde créée : '.$file);

        return File::exists($file);
    }
}
