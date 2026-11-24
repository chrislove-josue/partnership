<?php

namespace App\Services;

use App\Enums\CategoryGroup;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Partner;
use App\Models\User;

/**
 * Détermine l'église à créditer pour un don, selon la famille de catégorie :
 *  - Finances de l'église (et partenariat local) → église où le culte a eu lieu ;
 *  - Partenariat du ministère → église d'origine du partenaire.
 */
class DonationAttributionService
{
    public function __construct(private ChurchAccessService $access) {}

    public function churchFor(Partner $partner, CategoryDon $category, Church $worship): Church
    {
        if ($category->group === CategoryGroup::MINISTRY_PARTNERSHIP && $partner->church) {
            return $partner->church;
        }

        return $worship;
    }

    /**
     * Un acteur peut enregistrer un don là où le culte a eu lieu. Si l'attribution
     * va à l'église d'origine du partenaire (ministère), aucune autorisation
     * supplémentaire n'est requise sur cette église.
     */
    public function canRecord(User $actor, Church $worship, Church $attribution, Partner $partner): bool
    {
        if (! $this->access->canModifyChurchData($actor, $worship->id)) {
            return false;
        }

        if ($this->access->canModifyChurchData($actor, $attribution->id)) {
            return true;
        }

        return $attribution->id === $partner->church_id;
    }
}
