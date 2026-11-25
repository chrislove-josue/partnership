<?php

namespace App\Services;

use App\Enums\ChurchType;
use App\Models\Church;

/** Résout un église + toute sa descendance pour les filtres et totaux hiérarchiques. */
class ChurchDescendantService
{
    /**
     * @param  array<int, int>  $churchIds
     * @return array<int, int>
     */
    public function expand(array $churchIds): array
    {
        $all = array_map('intval', $churchIds);

        foreach ($churchIds as $id) {
            $church = Church::query()->find((int) $id);

            if ($church) {
                $all = array_merge($all, $church->descendantIds());
            }
        }

        return array_values(array_unique(array_map('intval', $all)));
    }

    /** @return array<int, int> */
    public function forChurch(int $churchId): array
    {
        return $this->expand([$churchId]);
    }

    /**
     * Pour un filtre : une église locale = elle-même ; un groupe/sous-groupe =
     * toute sa descendance.
     *
     * @return array<int, int>
     */
    public function forFilter(int $churchId): array
    {
        $church = Church::query()->find($churchId);

        if (! $church || $church->type === ChurchType::LOCAL) {
            return [$churchId];
        }

        return $this->forChurch($churchId);
    }
}
