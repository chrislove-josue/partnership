<?php

namespace App\Services;

use App\Enums\CategoryGroup;
use App\Enums\PeriodType;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Classement des partenaires par montant total donné sur une période.
 * La période est résolue par PeriodType (month / quarter / year) avec un
 * index (mois ou trimestre) et une année.
 */
class TopPartnerService
{
    /**
     * @param  array<int, int>  $churchIds
     * @param  CategoryGroup|null  $group  Famille de catégorie (null = toutes)
     * @return Collection<int, array{id: int, name: string, church: string|null, origin_church: string|null, cellule: string|null, amount: float, count: int, is_user: bool}>
     */
    public function rank(
        array $churchIds,
        PeriodType $period = PeriodType::MONTH,
        ?int $year = null,
        ?int $index = null,
        int $limit = 20,
        ?CategoryGroup $group = null,
        ?int $categoryId = null,
    ): Collection {
        $year = $year ?? (int) now()->year;
        $index = $index ?? (int) now()->month;

        [$from, $to] = $this->range($period, $year, $index);

        $donations = Donation::query()
            ->with('partner:id,first_name,last_name,church_id,cellule_id,user_id')
            ->whereIn('church_id', $churchIds)
            ->where('is_active', true)
            ->whereBetween('date', [$from, $to])
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when($group, function ($query) use ($group): void {
                $query->whereHas('category', fn ($q) => $q->where('group', $group->value));
            })
            ->get(['id', 'amount', 'partner_id', 'date']);

        $partnerIds = $donations->pluck('partner_id')->unique()->all();

        $partners = Partner::query()
            ->with(['church:id,name', 'cellule:id,name'])
            ->whereIn('id', $partnerIds)
            ->get()
            ->keyBy('id');

        // L'église affichée est celle à qui le don est crédité (l'église
        // sélectionnée), pas l'église d'origine du partenaire.
        $churchNames = Church::query()
            ->whereIn('id', array_map('intval', $churchIds))
            ->pluck('name', 'id');

        $totals = [];

        foreach ($donations as $donation) {
            $id = $donation->partner_id;
            $totals[$id] ??= ['amount' => 0.0, 'count' => 0];
            $totals[$id]['amount'] += (float) $donation->amount;
            $totals[$id]['count'] += 1;
        }

        return collect($totals)
            ->map(function (array $total, int $id) use ($partners, $churchNames): array {
                $partner = $partners->get($id);

                return [
                    'id' => $id,
                    'name' => $partner?->full_name ?? 'Partenaire inconnu',
                    // Toutes les lignes portent l'église sélectionnée : un seul
                    // classement, jamais un mélange d'églises.
                    'church' => $churchNames->first() ?? null,
                    'origin_church' => $partner?->church?->name,
                    'cellule' => $partner?->cellule?->name,
                    'amount' => $total['amount'],
                    'count' => $total['count'],
                    'is_user' => $partner?->isUserAccount() ?? false,
                ];
            })
            ->sortByDesc('amount')
            ->take($limit)
            ->values();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(PeriodType $period, int $year, int $index): array
    {
        return match ($period) {
            PeriodType::MONTH => [
                Carbon::create($year, max(1, min(12, $index)), 1)->startOfMonth(),
                Carbon::create($year, max(1, min(12, $index)), 1)->endOfMonth(),
            ],
            PeriodType::QUARTER => [
                Carbon::create($year, (max(1, min(4, $index)) - 1) * 3 + 1, 1)->startOfQuarter(),
                Carbon::create($year, (max(1, min(4, $index)) - 1) * 3 + 1, 1)->endOfQuarter(),
            ],
            PeriodType::YEAR => [
                Carbon::create($year, 1, 1)->startOfYear(),
                Carbon::create($year, 12, 31)->endOfYear(),
            ],
        };
    }
}
