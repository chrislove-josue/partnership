<?php

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Models\Church;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Maintient un compte de personnel (STAFF) actif par église.
 *
 * La commande est idempotente : elle ne crée que les comptes manquants,
 * réactive les comptes désactivés et n'écrase jamais un mot de passe existant.
 */
class EnsureChurchStaff extends Command
{
    protected $signature = 'church:ensure-staff
        {--church= : Limiter à une église (identifiant ou nom exact)}
        {--domain= : Domaine des adresses email des personnels}
        {--password= : Mot de passe initial des nouveaux comptes}';

    protected $description = 'Crée un personnel (STAFF) actif pour chaque église qui n’en a pas.';

    public function handle(): int
    {
        $role = Role::where('name', RoleName::STAFF->value)->first();

        if (! $role) {
            $this->error('Le rôle STAFF est introuvable. Lancez d’abord : php artisan db:seed --class=RoleAndPermissionSeeder');

            return self::FAILURE;
        }

        $domain = trim((string) ($this->option('domain') ?: config('staff.email_domain')));
        $password = (string) ($this->option('password') ?: config('staff.default_password'));

        if ($domain === '' || $password === '') {
            $this->error('Le domaine et le mot de passe des personnels ne peuvent pas être vides.');

            return self::FAILURE;
        }

        $filter = $this->option('church');
        $query = Church::query()->orderBy('id');

        if ($filter) {
            $query->where(fn ($q) => $q->where('id', $filter)->orWhere('name', $filter));
        }

        $churches = $query->get();

        if ($churches->isEmpty()) {
            $this->error('Aucune église correspondante.');

            return self::FAILURE;
        }

        $rows = [];
        $created = 0;
        $reactivated = 0;
        $unchanged = 0;

        foreach ($churches as $church) {
            $existing = User::where('assigned_church_id', $church->id)
                ->role(RoleName::STAFF->value)
                ->first();

            if ($existing) {
                if ($existing->is_active) {
                    $status = 'Déjà actif';
                    $unchanged++;
                } else {
                    $existing->update(['is_active' => true]);
                    $status = 'Réactivé';
                    $reactivated++;
                }

                $email = $existing->email;
            } else {
                $email = $this->availableEmail($church->name, $domain);
                $user = User::create([
                    'name' => 'Personnel '.$church->name,
                    'email' => $email,
                    'password' => $password,
                    'assigned_church_id' => $church->id,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);
                $user->assignRole($role);
                $user->syncImpliedRoles();

                $status = 'Créé';
                $created++;
            }

            $rows[] = [$church->name, $email, $status];
        }

        $this->table(['Église', 'Adresse email', 'Action'], $rows);
        $this->info(sprintf(
            '%d créé(s), %d réactivé(s), %d déjà présent(s).',
            $created,
            $reactivated,
            $unchanged,
        ));
        $this->line('Mot de passe initial des nouveaux comptes : '.$password);

        return self::SUCCESS;
    }

    private function availableEmail(string $churchName, string $domain): string
    {
        $base = 'staff.'.Str::slug($churchName);
        $email = "{$base}@{$domain}";
        $suffix = 2;

        while (User::where('email', $email)->exists()) {
            $email = "{$base}-{$suffix}@{$domain}";
            $suffix++;
        }

        return $email;
    }
}
