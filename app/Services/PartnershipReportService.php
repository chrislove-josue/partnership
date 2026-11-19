<?php

namespace App\Services;

use App\Enums\ChurchType;
use App\Enums\PeriodType;
use App\Enums\RoleName;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Models\User;
use Carbon\Carbon;
use RuntimeException;

/**
 * Port de lib/report.ts — rapport de partenariat d'une église sur une période.
 *
 * Les périodes sont strictement bornées : un mois va du 1er au dernier jour du
 * mois, un trimestre du 1er du premier mois au dernier du troisième, et une
 * année du 1er janvier au 31 décembre (aucun débordement sur l'année suivante).
 */
class PartnershipReportService
{
    private const MONTH_LABELS = [
        'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
        'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
    ];

    /**
     * @return array{from: Carbon, to: Carbon, label: string}
     */
    public function resolvePeriodRange(PeriodType $type, int $year, ?int $index): array
    {
        if ($type === PeriodType::MONTH) {
            $month = max(1, min($index ?? 1, 12));
            $from = Carbon::create($year, $month, 1, 0, 0, 0);

            return [
                'from' => $from,
                'to' => $from->copy()->endOfMonth(),
                'label' => self::MONTH_LABELS[$month - 1].' '.$year,
            ];
        }

        if ($type === PeriodType::QUARTER) {
            $quarter = max(1, min($index ?? 1, 4));
            $from = Carbon::create($year, (($quarter - 1) * 3) + 1, 1, 0, 0, 0);

            return [
                'from' => $from,
                'to' => $from->copy()->addMonths(2)->endOfMonth(),
                'label' => "T{$quarter} {$year}",
            ];
        }

        $from = Carbon::create($year, 1, 1, 0, 0, 0);

        return [
            'from' => $from,
            'to' => $from->copy()->endOfYear(),
            'label' => "Année {$year}",
        ];
    }

    /**
     * Construit le rapport complet d'une église (totaux, catégories,
     * partenaires, partenaires sans don et détail des dons).
     *
     * @return array<string, mixed>
     */
    public function buildReport(int $churchId, PeriodType $type, int $year, ?int $index): array
    {
        $church = Church::with('parent:id,name')->find($churchId);

        if (! $church) {
            throw new RuntimeException('Église introuvable');
        }

        $range = $this->resolvePeriodRange($type, $year, $index);

        $donations = Donation::with(['category:id,name,color', 'partner.cellule:id,name'])
            ->where('church_id', $churchId)
            ->whereBetween('date', [$range['from'], $range['to']])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $categories = [];
        $partners = [];
        $total = 0.0;

        foreach ($donations as $donation) {
            $amount = (float) $donation->amount;
            $total += $amount;

            $categoryId = $donation->category_id;
            if (! isset($categories[$categoryId])) {
                $categories[$categoryId] = [
                    'name' => $donation->category?->name ?? '—',
                    'color' => $donation->category?->color ?? '#1F4E78',
                    'amount' => 0.0,
                    'count' => 0,
                ];
            }
            $categories[$categoryId]['amount'] += $amount;
            $categories[$categoryId]['count']++;

            $partner = $donation->partner;
            if (! $partner) {
                continue;
            }

            $partnerId = $partner->id;
            if (! isset($partners[$partnerId])) {
                $partners[$partnerId] = [
                    'id' => $partnerId,
                    'name' => $partner->full_name,
                    'email' => $partner->email,
                    'phone' => $partner->phone,
                    'handleKC' => $partner->handle_kc,
                    'cellule' => $partner->cellule?->name,
                    'amount' => 0.0,
                    'count' => 0,
                ];
            }
            $partners[$partnerId]['amount'] += $amount;
            $partners[$partnerId]['count']++;
        }

        $categories = array_values($categories);
        usort($categories, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

        $partners = array_values($partners);
        usort($partners, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

        $partnersWithout = Partner::with('cellule:id,name')
            ->where('church_id', $churchId)
            ->whereDoesntHave('donations', function ($query) use ($churchId, $range): void {
                $query->where('church_id', $churchId)
                    ->whereBetween('date', [$range['from'], $range['to']]);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return [
            'church' => [
                'id' => $church->id,
                'name' => $church->name,
                'type' => $church->type instanceof ChurchType ? $church->type->value : (string) $church->type,
                'parentId' => $church->parent_id,
                'parentName' => $church->parent?->name,
            ],
            'period' => [
                'type' => $type->value,
                'index' => $index,
                'year' => $year,
                'label' => $range['label'],
                'from' => $range['from']->toIso8601String(),
                'to' => $range['to']->toIso8601String(),
            ],
            'totals' => [
                'amount' => $total,
                'donationCount' => $donations->count(),
                'partnersWithDonation' => count($partners),
                'partnersWithoutDonation' => $partnersWithout->count(),
                'registeredPartners' => count($partners) + $partnersWithout->count(),
            ],
            'categories' => $categories,
            'partners' => $partners,
            'partnersWithoutDonation' => $partnersWithout->map(fn (Partner $p): array => [
                'id' => $p->id,
                'name' => $p->full_name,
                'email' => $p->email,
                'phone' => $p->phone,
                'handleKC' => $p->handle_kc,
                'cellule' => $p->cellule?->name,
            ])->all(),
            'donations' => $donations->map(fn (Donation $d): array => [
                'id' => $d->id,
                'amount' => (float) $d->amount,
                'date' => $d->date?->toIso8601String(),
                'category' => $d->category?->name ?? '—',
                'partner' => $d->partner ? $d->partner->full_name : '—',
            ])->all(),
        ];
    }

    /** Format d'affichage des montants en FCFA. */
    public function formatAmount(float|int $amount): string
    {
        return number_format((float) $amount, 0, ',', ' ').' FCFA';
    }

    /**
     * Nom de fichier Excel, miroir de la version Next.js.
     *
     * @param  array<string, mixed>  $report
     */
    public function fileName(array $report): string
    {
        $church = preg_replace('/[^a-zA-Z0-9]+/', '_', $report['church']['name']) ?: 'eglise';
        $period = preg_replace('/\s+/', '_', $report['period']['label']) ?: 'periode';

        return "rapport_partenariat_{$church}_{$period}.xlsx";
    }

    /**
     * Résolution de la chaîne hiérarchique des destinataires.
     *
     * STAFF → pasteur de son église ; pasteur LOCAL/SUBGROUP → niveau parent ;
     * pasteur de groupe / SUPER_ADMIN → super administrateurs.
     * L'adresse de l'expéditeur est toujours exclue.
     *
     * @return array{recipients: array<int, array{email: string, name: string}>, targetLabel: string, chain: array<int, string>}
     */
    public function resolveRecipients(User $user, int $churchId): array
    {
        /** @var array<string, array{email: string, name: string}> $dedupe */
        $dedupe = [];
        $chain = [];
        $senderEmail = $user->email;

        $addManagers = function (Church $church, string $label) use (&$dedupe, &$chain, $senderEmail): bool {
            $full = Church::with('manager:id,email,name')->find($church->id);
            $manager = $full?->manager;

            if (! $manager || ! $manager->email) {
                return false;
            }

            $chain[] = $label;
            if ($manager->email !== $senderEmail) {
                $dedupe[$manager->email] = [
                    'email' => $manager->email,
                    'name' => $manager->name ?: $manager->email,
                ];
            }

            return true;
        };

        $addSuperAdmins = function () use (&$dedupe, &$chain, $senderEmail): void {
            $chain[] = 'super administrateurs';

            User::role(RoleName::SUPER_ADMIN->value)
                ->where('is_active', true)
                ->get(['id', 'email', 'name'])
                ->each(function (User $admin) use (&$dedupe, $senderEmail): void {
                    if ($admin->email && $admin->email !== $senderEmail) {
                        $dedupe[$admin->email] = [
                            'email' => $admin->email,
                            'name' => $admin->name ?: $admin->email,
                        ];
                    }
                });
        };

        $church = Church::find($churchId);

        if (! $church) {
            throw new RuntimeException('Église introuvable');
        }

        // Le groupe est le sommet de la chaîne locale.
        if ($church->type === ChurchType::GROUP) {
            $addSuperAdmins();

            return [
                'recipients' => array_values($dedupe),
                'targetLabel' => 'hiérarchie nationale (super administrateurs)',
                'chain' => $chain,
            ];
        }

        $senderManagesThisChurch = $church->manager_id === $user->id;
        $isHigherPastor = in_array($user->roleName(), [
            RoleName::SUPER_ADMIN->value,
            RoleName::GROUP_PASTOR->value,
            RoleName::SUBGROUP_PASTOR->value,
        ], true);

        if ($senderManagesThisChurch || $isHigherPastor) {
            $parent = $church->parent_id ? Church::find($church->parent_id) : null;
            $foundParent = null;

            while ($parent) {
                $label = $parent->type === ChurchType::SUB_GROUP
                    ? "pasteur de sous-groupe ({$parent->name})"
                    : "pasteur de groupe ({$parent->name})";

                if ($addManagers($parent, $label)) {
                    $foundParent = $parent;
                    break;
                }

                $parent = $parent->parent_id ? Church::find($parent->parent_id) : null;
            }

            if ($dedupe === []) {
                $addSuperAdmins();
            }

            return [
                'recipients' => array_values($dedupe),
                'targetLabel' => $foundParent
                    ? ($foundParent->type === ChurchType::SUB_GROUP
                        ? 'pasteur de votre sous-groupe'
                        : 'pasteur de votre groupe')
                    : 'hiérarchie nationale (super administrateurs)',
                'chain' => $chain,
            ];
        }

        // STAFF (ou membre non responsable) : pasteur de sa propre église.
        $ownFound = $addManagers($church, "pasteur de votre église ({$church->name})");

        if (! $ownFound) {
            $parent = $church->parent_id ? Church::find($church->parent_id) : null;
            if ($parent) {
                $addManagers($parent, "pasteur de groupe ({$parent->name})");
            }
            if ($dedupe === []) {
                $addSuperAdmins();
            }
        }

        return [
            'recipients' => array_values($dedupe),
            'targetLabel' => 'pasteur de votre église',
            'chain' => $chain,
        ];
    }
}
