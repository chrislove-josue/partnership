<?php

namespace App\Services;

use App\Models\Cellule;
use App\Models\Donation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Port de la logique de tendances de app/dashboard/reports/page.tsx
 * (variation mensuelle des dons par partenaire, église et cellule).
 */
class DonationTrendService
{
    public const PALETTE = [
        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
        '#ec4899', '#6366f1', '#14b8a6', '#f97316', '#06b6d4',
    ];

    /**
     * Variation mensuelle des dons par partenaire (top N).
     *
     * @param  array<int, int>  $churchIds
     * @return array{labels: array<int, string>, series: array<int, array{label: string, data: array<int, float>}>}
     */
    public function byPartner(array $churchIds, int $limit = 10): array
    {
        $donations = $this->donations($churchIds);

        $groups = [];
        foreach ($donations as $donation) {
            if (! $donation->partner_id) {
                continue;
            }

            $groups[$donation->partner_id]['label'] = $donation->partner?->full_name
                ?? 'Partenaire #'.$donation->partner_id;
            $groups[$donation->partner_id]['total'] = ($groups[$donation->partner_id]['total'] ?? 0)
                + (float) $donation->amount;
        }

        return $this->build($donations, fn (Donation $d) => [$d->partner_id], $groups, $limit);
    }

    /**
     * Variation mensuelle des dons par église.
     *
     * @param  array<int, int>  $churchIds
     * @return array{labels: array<int, string>, series: array<int, array{label: string, data: array<int, float>}>}
     */
    public function byChurch(array $churchIds, int $limit = 0): array
    {
        $donations = $this->donations($churchIds);

        $groups = [];
        foreach ($donations as $donation) {
            if (! $donation->church_id) {
                continue;
            }

            $groups[$donation->church_id]['label'] = $donation->church?->name
                ?? 'Église #'.$donation->church_id;
            $groups[$donation->church_id]['total'] = ($groups[$donation->church_id]['total'] ?? 0)
                + (float) $donation->amount;
        }

        return $this->build($donations, fn (Donation $d) => [$d->church_id], $groups, $limit);
    }

    /**
     * Variation mensuelle des dons par cellule (top N).
     *
     * Un don appartient à une cellule si le donateur en est membre
     * (partner.cellule_id) ou s'il en est le leader (partner.id = cellule.leader_id).
     *
     * @param  array<int, int>  $churchIds
     * @return array{labels: array<int, string>, series: array<int, array{label: string, data: array<int, float>}>}
     */
    public function byCellule(array $churchIds, int $limit = 10): array
    {
        $donations = $this->donations($churchIds)->filter(fn (Donation $d) => $d->partner !== null);

        $cellules = Cellule::query()
            ->whereIn('church_id', $churchIds)
            ->get(['id', 'name', 'leader_id']);

        $byId = $cellules->keyBy('id');
        $byLeader = $cellules->whereNotNull('leader_id')->keyBy('leader_id');

        $keysFor = function (Donation $donation) use ($byId, $byLeader): array {
            $partner = $donation->partner;
            $matches = [];

            if ($partner->cellule_id && $byId->has($partner->cellule_id)) {
                $matches[] = $partner->cellule_id;
            }

            if ($byLeader->has($partner->id)) {
                $matches[] = $byLeader->get($partner->id)->id;
            }

            return array_values(array_unique($matches));
        };

        $groups = [];
        foreach ($donations as $donation) {
            foreach ($keysFor($donation) as $celluleId) {
                $groups[$celluleId]['label'] = $byId->get($celluleId)?->name ?? 'Cellule #'.$celluleId;
                $groups[$celluleId]['total'] = ($groups[$celluleId]['total'] ?? 0) + (float) $donation->amount;
            }
        }

        return $this->build($donations, $keysFor, $groups, $limit);
    }

    /**
     * @param  array<int, int>  $churchIds
     * @return Collection<int, Donation>
     */
    private function donations(array $churchIds): Collection
    {
        if (empty($churchIds)) {
            return collect();
        }

        return Donation::query()
            ->whereIn('church_id', $churchIds)
            ->with([
                'partner:id,first_name,last_name,cellule_id',
                'church:id,name',
            ])
            ->get(['id', 'amount', 'date', 'partner_id', 'church_id']);
    }

    /**
     * Construit les séries mensuelles pour les groupes sélectionnés.
     *
     * @param  Collection<int, Donation>  $donations
     * @param  callable(Donation): array<int, int|string|null>  $keysFor
     * @param  array<int|string, array{label: string, total: float}>  $groups
     * @return array{labels: array<int, string>, series: array<int, array{label: string, data: array<int, float>}>}
     */
    private function build(Collection $donations, callable $keysFor, array $groups, int $limit): array
    {
        $ordered = collect($groups)->sortByDesc('total');

        if ($limit > 0) {
            $ordered = $ordered->take($limit);
        }

        $selected = $ordered->keys()->all();
        $selectedSet = array_flip($selected);

        $monthKeys = [];
        foreach ($donations as $donation) {
            $monthKeys[$donation->date->format('Y-m')] = true;
        }
        $monthKeys = array_keys($monthKeys);
        sort($monthKeys);

        $totals = [];
        foreach ($donations as $donation) {
            $ym = $donation->date->format('Y-m');

            foreach ($keysFor($donation) as $key) {
                if ($key === null || ! isset($selectedSet[$key])) {
                    continue;
                }

                $totals[$key][$ym] = ($totals[$key][$ym] ?? 0) + (float) $donation->amount;
            }
        }

        $labels = array_map(
            fn (string $ym) => Carbon::parse($ym.'-01')->translatedFormat('M Y'),
            $monthKeys
        );

        $series = [];
        foreach ($selected as $key) {
            $series[] = [
                'label' => $groups[$key]['label'],
                'data' => array_map(
                    fn (string $ym) => round($totals[$key][$ym] ?? 0, 2),
                    $monthKeys
                ),
            ];
        }

        return ['labels' => $labels, 'series' => $series];
    }
}
