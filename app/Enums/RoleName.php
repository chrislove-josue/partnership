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
