<?php

namespace App\Services;

use App\Enums\PeriodType;
use App\Models\Church;
use App\Models\Donation;
use Illuminate\Support\Collection;

/** Classement des églises par montant total donné sur une période. */
class TopChurchService
{
    /**
     * @param  array<int, int>  $churchIds
     * @return Collection<int, array{id: int, name: string, type: string, amount: float, count: int, partners: int}>
     */
    public function rank(
        array $churchIds,
        PeriodType $period = PeriodType::MONTH,
        ?int $year = null,
        ?int $index = null,
        int $limit = 10,
    ): Collection {
        $year = $year ?? (int) now()->year;
        $index = $index ?? (int) now()->month;

        [$from, $to] = app(TopPartnerService::class)->range($period, $year, $index);

        $allChurchIds = app(ChurchDescendantService::class)->expand($churchIds);

        $donations = Donation::query()
            ->whereIn('church_id', $allChurchIds)
            ->where('is_active', true)
            ->whereBetween('date', [$from, $to])
            ->get(['id', 'amount', 'partner_id', 'church_id']);

        $churches = Church::query()
            ->whereIn('id', $allChurchIds)
            ->get(['id', 'name', 'type'])
            ->keyBy('id');

        $totals = [];

        foreach ($donations as $donation) {
            $id = $donation->church_id;
            $totals[$id] ??= ['amount' => 0.0, 'count' => 0, 'partners' => []];
            $totals[$id]['amount'] += (float) $donation->amount;
            $totals[$id]['count'] += 1;
            $totals[$id]['partners'][$donation->partner_id] = $donation->partner_id;
        }

        return collect($totals)
            ->map(function (array $total, int $id) use ($churches): array {
                $church = $churches->get($id);

                return [
                    'id' => $id,
                    'name' => $church?->name ?? 'Église inconnue',
                    'type' => $church?->type?->label() ?? '',
                    'amount' => $total['amount'],
                    'count' => $total['count'],
                    'partners' => count($total['partners']),
                ];
            })
            ->sortByDesc('amount')
            ->take($limit)
            ->values();
    }
}
