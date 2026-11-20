<?php

namespace App\Filament\Resources\Users\Pages\Concerns;

use App\Models\Role;
use App\Services\ChurchAccessService;
use Illuminate\Validation\ValidationException;

/**
 * Contrôle serveur de l'attribution des rôles dans les formulaires Filament.
 * Le filtre du champ « Rôles » n'est qu'une aide : cette vérification empêche
 * qu'un rôle ou une église non autorisés soient envoyés directement.
 */
trait ValidatesUserAssignment
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function assertAssignableRoles(array $data, ?int $fallbackChurchId = null): void
    {
        $actor = auth()->user();

        if (! $actor) {
            return;
        }

        $roles = collect($data['roles'] ?? [])
            ->map(function (mixed $value): ?string {
                if (is_numeric($value)) {
                    return Role::find($value)?->name;
                }

                return is_string($value) && $value !== '' ? $value : null;
            })
            ->filter()
            ->values()
            ->all();

        $churchId = (int) ($data['assigned_church_id'] ?? $fallbackChurchId ?? 0);

        if (! app(ChurchAccessService::class)->canAssignRoles($actor, $roles, $churchId)) {
            throw ValidationException::withMessages([
                'roles' => 'Vous ne pouvez pas attribuer ce rôle dans cette église.',
            ]);
        }
    }
}
