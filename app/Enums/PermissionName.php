<?php

namespace App\Enums;

enum PermissionName: string
{
    // Lecture des données
    case READ_OWN_CHURCH_DATA = 'READ_OWN_CHURCH_DATA';
    case READ_CHILD_CHURCHES_DATA = 'READ_CHILD_CHURCHES_DATA';

    // Gestion des utilisateurs
    case MANAGE_SUBGROUP_PASTORS = 'MANAGE_SUBGROUP_PASTORS';
    case MANAGE_LOCAL_PASTORS = 'MANAGE_LOCAL_PASTORS';
    case MANAGE_STAFF = 'MANAGE_STAFF';

    // Gestion des églises
    case MANAGE_CHURCHES = 'MANAGE_CHURCHES';

    // Dashboard & profil
    case VIEW_DASHBOARD = 'VIEW_DASHBOARD';
    case MANAGE_OWN_PROFILE = 'MANAGE_OWN_PROFILE';

    public function label(): string
    {
        return match ($this) {
            self::READ_OWN_CHURCH_DATA => 'Lire les données de son église',
            self::READ_CHILD_CHURCHES_DATA => 'Lire les données des églises filles',
            self::MANAGE_SUBGROUP_PASTORS => 'Gérer les pasteurs de sous-groupes',
            self::MANAGE_LOCAL_PASTORS => 'Gérer les pasteurs locaux',
            self::MANAGE_STAFF => 'Gérer le personnel',
            self::MANAGE_CHURCHES => 'Gérer les églises',
            self::VIEW_DASHBOARD => 'Voir le tableau de bord',
            self::MANAGE_OWN_PROFILE => 'Gérer son profil',
        };
    }
}
