<?php

namespace App\Enums;

/**
 * Les catégories de dons sont regroupées en trois grandes familles, pour
 * distinguer les finances propres de l'église, le partenariat avec le ministère
 * (Christ Embassy) et le partenariat local (projets de l'église).
 */
enum CategoryGroup: string
{
    case CHURCH_FINANCES = 'church_finances';
    case MINISTRY_PARTNERSHIP = 'ministry_partnership';
    case LOCAL_PARTNERSHIP = 'local_partnership';

    public function label(): string
    {
        return match ($this) {
            self::CHURCH_FINANCES => 'Finances de l\'église',
            self::MINISTRY_PARTNERSHIP => 'Partenariat du ministère',
            self::LOCAL_PARTNERSHIP => 'Partenariat local',
        };
    }

    /**
     * Devine la famille d'une catégorie à partir de son libellé. Utilisé pour
     * le remplissage initial et l'import de données existantes.
     */
    public static function fromName(string $name): self
    {
        return match (mb_strtolower(trim($name))) {
            'dîmes', 'dime', 'dimes', 'dîme',
            'offrandes', 'offrande',
            'action de grâce', 'action de grace',
            'semences', 'semence',
            'action de grâces', => self::CHURCH_FINANCES,
            'prémices', 'premices', => self::MINISTRY_PARTNERSHIP,
            'rhapsodie des réalités', 'rhapsodie des realites', 'rhapsodie de réalités', 'rhapsodie de realites',
            'mission innercity', 'mission inner city', 'mission urbaine',
            'école de guérisons', 'ecole de guerisons', 'ecole de guérisons', 'healing streams', => self::MINISTRY_PARTNERSHIP,
            default => self::LOCAL_PARTNERSHIP,
        };
    }
}
