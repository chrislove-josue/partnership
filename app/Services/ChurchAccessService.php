<?php

namespace App\Services;

use App\Enums\ChurchType;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Church;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Port de lib/accessControl.ts — autorisation hiérarchique basée sur
 * l'arbre des églises (GROUP → SUB_GROUP → LOCAL).
 */
class ChurchAccessService
{
    // ─────────────────────────────────────────────────────────────────────
    // LECTURE
    // ─────────────────────────────────────────────────────────────────────

    public function canAccessChurchData(User $user, int $targetChurchId): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if ($user->assigned_church_id === $targetChurchId) {
            return true;
        }

        $canReadHierarchy = in_array($user->roleName(), [
            RoleName::GROUP_PASTOR->value,
            RoleName::SUBGROUP_PASTOR->value,
        ], true) || $user->hasPermission(PermissionName::READ_CHILD_CHURCHES_DATA);

        if (! $canReadHierarchy) {
            return false;
        }

        $targetChurch = Church::with('parent.parent')->find($targetChurchId);

        if (! $targetChurch) {
            return false;
        }

        $effectiveType = $user->assignedChurch?->type
            ?? $this->typeFromRole($user->roleName());

        if ($effectiveType === ChurchType::GROUP) {
            return $targetChurch->parent_id === $user->assigned_church_id
                || $targetChurch->parent?->parent_id === $user->assigned_church_id;
        }

        if ($effectiveType === ChurchType::SUB_GROUP) {
            return $targetChurch->parent_id === $user->assigned_church_id;
        }

        return false;
    }

    // ─────────────────────────────────────────────────────────────────────
    // ÉCRITURE / MODIFICATION / SUPPRESSION
    // ─────────────────────────────────────────────────────────────────────

    public function canModifyChurchData(User $user, int $targetChurchId): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if ($user->assigned_church_id === $targetChurchId) {
            return true;
        }

        $role = $user->roleName();

        if (! in_array($role, [RoleName::GROUP_PASTOR->value, RoleName::SUBGROUP_PASTOR->value], true)) {
            return false;
        }

        $targetChurch = Church::with('parent.parent')->find($targetChurchId);

        if (! $targetChurch) {
            return false;
        }

        if ($role === RoleName::GROUP_PASTOR->value) {
            return $targetChurch->parent_id === $user->assigned_church_id
                || $targetChurch->parent?->parent_id === $user->assigned_church_id;
        }

        return $targetChurch->parent_id === $user->assigned_church_id;
    }

    // ─────────────────────────────────────────────────────────────────────
    // GESTION DES UTILISATEURS
    // ─────────────────────────────────────────────────────────────────────

    public function canManageUser(User $actor, string $targetRole): bool
    {
        if ($this->isSuperAdmin($actor)) {
            return true;
        }

        $role = RoleName::tryFrom($actor->roleName() ?? '');

        if (! $role) {
            return false;
        }

        return in_array($targetRole, array_map(
            fn (RoleName $r) => $r->value,
            $role->manageableRoles()
        ), true);
    }

    public function canManageUsers(User $user): bool
    {
        return in_array($user->roleName(), [
            RoleName::SUPER_ADMIN->value,
            RoleName::GROUP_PASTOR->value,
            RoleName::SUBGROUP_PASTOR->value,
            RoleName::LOCAL_PASTOR->value,
        ], true);
    }

    // ─────────────────────────────────────────────────────────────────────
    // GESTION DES ÉGLISES
    // ─────────────────────────────────────────────────────────────────────

    public function canCreateChurch(User $user, ChurchType $churchType): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $role = RoleName::tryFrom($user->roleName() ?? '');

        if (! $role) {
            return false;
        }

        return in_array($churchType, $role->creatableChurchTypes(), true);
    }

    public function canManageChurches(User $user): bool
    {
        return $this->isSuperAdmin($user)
            || $user->roleName() === RoleName::GROUP_PASTOR->value
            || $user->hasPermission(PermissionName::MANAGE_CHURCHES);
    }

    // ─────────────────────────────────────────────────────────────────────
    // CATÉGORIES & RÔLES
    // ─────────────────────────────────────────────────────────────────────

    public function canCrudCategoryAndRole(User $user): bool
    {
        return in_array($user->roleName(), [
            RoleName::SUPER_ADMIN->value,
            RoleName::GROUP_PASTOR->value,
        ], true);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PROFIL
    // ─────────────────────────────────────────────────────────────────────

    public function canManageOwnProfile(User $user, int $targetUserId): bool
    {
        return $user->id === $targetUserId
            || $user->hasPermission(PermissionName::MANAGE_OWN_PROFILE);
    }

    // ─────────────────────────────────────────────────────────────────────
    // ACCÈS ADMIN / DASHBOARD
    // ─────────────────────────────────────────────────────────────────────

    public function canAccessAdminFeatures(User $user): bool
    {
        return in_array($user->roleName(), [
            RoleName::SUPER_ADMIN->value,
            RoleName::GROUP_PASTOR->value,
        ], true);
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS : LISTES D'ÉGLISES ACCESSIBLES
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return Collection<int, Church>
     */
    public function accessibleChurches(User $user): Collection
    {
        if ($this->isSuperAdmin($user)) {
            return Church::all();
        }

        $baseChurch = Church::with('children.children')->find($user->assigned_church_id);

        if (! $baseChurch) {
            return collect();
        }

        $canReadHierarchy = $user->roleName() === RoleName::GROUP_PASTOR->value
            || $user->hasPermission(PermissionName::READ_CHILD_CHURCHES_DATA);

        if ($canReadHierarchy) {
            if ($baseChurch->type === ChurchType::GROUP) {
                $subgroups = $baseChurch->children;
                $locals = $subgroups->flatMap(fn (Church $sg) => $sg->children);

                return collect([$baseChurch])->merge($subgroups)->merge($locals);
            }

            if ($baseChurch->type === ChurchType::SUB_GROUP) {
                return collect([$baseChurch])->merge($baseChurch->children);
            }
        }

        return collect([$baseChurch]);
    }

    /**
     * @return array<int, int>
     */
    public function accessibleChurchIds(User $user): array
    {
        return $this->accessibleChurches($user)->pluck('id')->all();
    }

    /**
     * Filtre une collection d'items possédant church_id par les églises accessibles.
     *
     * @template T
     * @param  Collection<int, T>  $items
     * @return Collection<int, T>
     */
    public function filterByAccessibleChurches(User $user, Collection $items): Collection
    {
        if ($this->isSuperAdmin($user)) {
            return $items;
        }

        $ids = $this->accessibleChurchIds($user);

        return $items->filter(fn ($item) => in_array($item->church_id, $ids, true))->values();
    }

    // ─────────────────────────────────────────────────────────────────────

    private function isSuperAdmin(User $user): bool
    {
        return $user->roleName() === RoleName::SUPER_ADMIN->value;
    }

    private function typeFromRole(?string $role): ?ChurchType
    {
        return match ($role) {
            RoleName::GROUP_PASTOR->value => ChurchType::GROUP,
            RoleName::SUBGROUP_PASTOR->value => ChurchType::SUB_GROUP,
            default => null,
        };
    }
}
