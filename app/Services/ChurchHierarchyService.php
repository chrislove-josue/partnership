<?php

namespace App\Services;

use App\Enums\ChurchType;
use App\Models\Church;
use Illuminate\Support\Collection;

/**
 * Règles de la structure des églises (une seule source de vérité pour
 * Filament, l'API mobile et les validations).
 *
 * GROUP     = racine (Bénin Groupe 1)
 * SUB_GROUP = enfant d'un GROUP ou d'un SUB_GROUP (Sous-groupe 1 / 2)
 * LOCAL     = enfant d'un SUB_GROUP ou d'un GROUP (église locale)
 */
class ChurchHierarchyService
{
    /** Types de parent autorisés pour un enfant du type donné. */
    public function allowedParentTypes(ChurchType $type): array
    {
        return match ($type) {
            ChurchType::GROUP => [],
            ChurchType::SUB_GROUP => [ChurchType::GROUP, ChurchType::SUB_GROUP],
            ChurchType::LOCAL => [ChurchType::GROUP, ChurchType::SUB_GROUP],
        };
    }

    public function isAllowedParent(ChurchType $childType, ?Church $parent): bool
    {
        if ($childType === ChurchType::GROUP) {
            return $parent === null;
        }

        if (! $parent) {
            return false;
        }

        return in_array($parent->type, $this->allowedParentTypes($childType), true);
    }

    /** Un parent ne peut pas être l'église elle-même ni l'une de ses descendantes. */
    public function wouldCreateCycle(?Church $church, ?int $parentId): bool
    {
        if (! $church || ! $parentId) {
            return false;
        }

        if ($parentId === $church->id) {
            return true;
        }

        return in_array($parentId, $church->descendantIds(), true);
    }

    /** Message d'erreur explicite pour un parent invalide. */
    public function parentErrorMessage(ChurchType $childType, ?Church $parent): string
    {
        if ($childType === ChurchType::GROUP) {
            return 'Un groupe ne peut pas avoir d’église parente.';
        }

        if (! $parent) {
            return 'Ce type d’église doit avoir une église parente.';
        }

        return 'Le parent doit être un groupe ou un sous-groupe (reçu : '.$parent->type->label().').';
    }

    /** Toutes les églises accessibles, à PROFONDEUR ILLIMITÉE (plus de 2 niveaux). */
    public function accessibleChurches(\App\Models\User $user): Collection
    {
        $access = app(ChurchAccessService::class);

        if ($user->isSuperAdmin()) {
            return Church::query()->orderBy('id')->get();
        }

        $base = $user->assignedChurch;

        if (! $base) {
            return collect();
        }

        $canReadHierarchy = in_array($user->roleName(), [
            \App\Enums\RoleName::GROUP_PASTOR->value,
            \App\Enums\RoleName::SUBGROUP_PASTOR->value,
        ], true) || $user->hasPermission(\App\Enums\PermissionName::READ_CHILD_CHURCHES_DATA);

        if (! $canReadHierarchy) {
            return collect([$base]);
        }

        $ids = array_merge([$base->id], $base->descendantIds());

        return Church::query()->with('parent:id,name')->whereIn('id', $ids)->orderBy('id')->get();
    }
}
