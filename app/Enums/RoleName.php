<?php

namespace App\Enums;

enum RoleName: string
{
    case SUPER_ADMIN = 'SUPER_ADMIN';
    case GROUP_PASTOR = 'GROUP_PASTOR';
    case SUBGROUP_PASTOR = 'SUBGROUP_PASTOR';
    case LOCAL_PASTOR = 'LOCAL_PASTOR';
    case STAFF = 'STAFF';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super administrateur',
            self::GROUP_PASTOR => 'Pasteur de groupe',
            self::SUBGROUP_PASTOR => 'Pasteur de sous-groupe',
            self::LOCAL_PASTOR => 'Pasteur local',
            self::STAFF => 'Personnel',
        };
    }

    /**
     * Rang utilisé pour déterminer le rôle principal d'un compte qui cumule
     * plusieurs fonctions. Le rang le plus élevé prend le pas.
     */
    public function rank(): int
    {
        return match ($this) {
            self::SUPER_ADMIN => 5,
            self::GROUP_PASTOR => 4,
            self::SUBGROUP_PASTOR => 3,
            self::LOCAL_PASTOR => 2,
            self::STAFF => 1,
        };
    }

    /**
     * Fonctions inférieures remplies par ce rôle.
     * Un pasteur de sous-groupe est aussi pasteur local ; un pasteur de groupe
     * est aussi pasteur de sous-groupe et pasteur local.
     *
     * @return array<int, self>
     */
    public function impliedRoles(): array
    {
        return match ($this) {
            self::GROUP_PASTOR => [self::SUBGROUP_PASTOR, self::LOCAL_PASTOR],
            self::SUBGROUP_PASTOR => [self::LOCAL_PASTOR],
            default => [],
        };
    }

    /**
     * Ce rôle et toutes les fonctions qu'il implique.
     *
     * @return array<int, self>
     */
    public function withImpliedRoles(): array
    {
        return collect([$this, ...$this->impliedRoles()])
            ->unique(fn (self $role): string => $role->value)
            ->values()
            ->all();
    }

    /**
     * Permissions attribuées par défaut à ce rôle (miroir de types/auth.ts).
     *
     * @return array<int, PermissionName>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SUPER_ADMIN => PermissionName::cases(),

            self::GROUP_PASTOR => [
                PermissionName::READ_OWN_CHURCH_DATA,
                PermissionName::READ_CHILD_CHURCHES_DATA,
                PermissionName::MANAGE_SUBGROUP_PASTORS,
                PermissionName::MANAGE_LOCAL_PASTORS,
                PermissionName::MANAGE_STAFF,
                PermissionName::MANAGE_CHURCHES,
                PermissionName::VIEW_DASHBOARD,
                PermissionName::MANAGE_OWN_PROFILE,
            ],

            self::SUBGROUP_PASTOR => [
                PermissionName::READ_OWN_CHURCH_DATA,
                PermissionName::READ_CHILD_CHURCHES_DATA,
                PermissionName::MANAGE_LOCAL_PASTORS,
                PermissionName::MANAGE_STAFF,
                PermissionName::VIEW_DASHBOARD,
                PermissionName::MANAGE_OWN_PROFILE,
            ],

            self::LOCAL_PASTOR => [
                PermissionName::READ_OWN_CHURCH_DATA,
                PermissionName::MANAGE_STAFF,
                PermissionName::VIEW_DASHBOARD,
                PermissionName::MANAGE_OWN_PROFILE,
            ],

            self::STAFF => [
                PermissionName::READ_OWN_CHURCH_DATA,
                PermissionName::VIEW_DASHBOARD,
                PermissionName::MANAGE_OWN_PROFILE,
            ],
        };
    }

    /**
     * Rôles qu'un acteur peut gérer (créer/modifier/supprimer).
     * SUPER_ADMIN gère tout (traité séparément).
     *
     * @return array<int, self>
     */
    public function manageableRoles(): array
    {
        return match ($this) {
            self::GROUP_PASTOR => [self::SUBGROUP_PASTOR, self::LOCAL_PASTOR, self::STAFF],
            self::SUBGROUP_PASTOR => [self::LOCAL_PASTOR, self::STAFF],
            self::LOCAL_PASTOR => [self::STAFF],
            default => [],
        };
    }

    /**
     * Types d'église qu'un acteur peut créer.
     *
     * @return array<int, ChurchType>
     */
    public function creatableChurchTypes(): array
    {
        return match ($this) {
            self::GROUP_PASTOR => [ChurchType::SUB_GROUP, ChurchType::LOCAL],
            self::SUBGROUP_PASTOR => [ChurchType::LOCAL],
            default => [],
        };
    }
}
