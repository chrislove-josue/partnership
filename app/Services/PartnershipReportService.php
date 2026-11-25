<?php

namespace App\Services;

use App\Enums\CategoryGroup;
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
    public function __construct(private readonly ReportCache $cache) {}

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
        return $this->cache->remember(
            $churchId,
            $type,
            $year,
            $index,
            fn (): array => $this->buildFreshReport($churchId, $type, $year, $index),
        );
    }

    /** @return array<string, mixed> */
    private function buildFreshReport(int $churchId, PeriodType $type, int $year, ?int $index): array
    {
        $church = Church::with('parent:id,name')->find($churchId);

        if (! $church) {
            throw new RuntimeException('Église introuvable');
        }

        $range = $this->resolvePeriodRange($type, $year, $index);

        $donations = Donation::with(['category:id,name,color,group', 'partner.cellule:id,name'])
            ->where('church_id', $churchId)
            ->where('is_active', true)
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
                    'group' => $donation->category?->group?->value ?? 'local_partnership',
                    'group_label' => $donation->category?->group?->label() ?? 'Partenariat local',
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
                    ->where('is_active', true)
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
                'hierarchyPath' => collect($church->ancestorIds())
                    ->map(fn (int $id): string => Church::query()->whereKey($id)->value('name') ?? '?')
                    ->push($church->name)
                    ->implode(' › '),
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

    /**
     * Filtre un rapport déjà construit pour ne conserver que les catégories (et les
     * dons) d'une famille donnée. Les totaux sont recalculés en conséquence.
     * Une famille vide/null conserve le rapport tel quel.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public function filterByCategoryGroup(array $report, ?string $group): array
    {
        $group = $group === '' ? null : $group;

        if ($group === null) {
            return $report;
        }

        $filteredCategories = array_values(array_filter(
            $report['categories'] ?? [],
            fn (array $category): bool => ($category['group'] ?? '') === $group,
        ));

        $filteredCategoryNames = array_flip(array_column($filteredCategories, 'name'));

        $filteredDonations = array_values(array_filter(
            $report['donations'] ?? [],
            fn (array $donation): bool => isset($filteredCategoryNames[$donation['category'] ?? '']),
        ));

        // Recalcul des montants par partenaire à partir des dons filtrés.
        $partnerAmounts = [];
        $partnerCounts = [];

        foreach ($filteredDonations as $donation) {
            $partnerName = $donation['partner'] ?? '—';

            if (! isset($partnerAmounts[$partnerName])) {
                $partnerAmounts[$partnerName] = 0.0;
                $partnerCounts[$partnerName] = 0;
            }

            $partnerAmounts[$partnerName] += (float) $donation['amount'];
            $partnerCounts[$partnerName]++;
        }

        $originalPartners = [];

        foreach ($report['partners'] ?? [] as $partner) {
            $originalPartners[$partner['name']] = $partner;
        }

        $partners = [];

        foreach ($partnerAmounts as $name => $amount) {
            $partner = $originalPartners[$name] ?? ['name' => $name];
            $partner['amount'] = $amount;
            $partner['count'] = $partnerCounts[$name];
            $partners[] = $partner;
        }

        usort($partners, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

        $total = 0.0;

        foreach ($filteredDonations as $donation) {
            $total += (float) $donation['amount'];
        }

        $report['categories'] = $filteredCategories;
        $report['donations'] = $filteredDonations;
        $report['partners'] = $partners;
        $report['totals']['amount'] = $total;
        $report['totals']['donationCount'] = count($filteredDonations);
        $report['totals']['partnersWithDonation'] = count($partners);
        $report['category_group'] = $group;
        $report['category_group_label'] = CategoryGroup::tryFrom($group)?->label() ?? $group;

        return $report;
    }

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

        $group = (string) ($report['category_group'] ?? '');
        $groupSlug = $group === ''
            ? 'toutes_familles'
            : (preg_replace('/[^a-zA-Z0-9]+/', '_', $group) ?: 'famille');

        $datetime = now()->format('Y-m-d_H-i-s');

        return "rapport_partenariat_{$church}_{$groupSlug}_{$datetime}.xlsx";
    }

    /**
     * Résolution de la chaîne hiérarchique des destinataires.
     *
     * Le personnel envoie son rapport au pasteur de SA église, quel que soit son
     * niveau (locale, sous-groupe ou groupe), avec le niveau supérieur en copie.
     * Les pasteurs remontent d'un niveau : locale → sous-groupe → groupe, avec le
     * niveau suivant en copie. Les super administrateurs sont en copie de chaque
     * niveau, et destinataires pour une église groupe (pasteur) ou un SUPER_ADMIN.
     * L'adresse de l'expéditeur est toujours exclue.
     *
     * @return array{
     *     recipients: array<int, array{email: string, name: string}>,
     *     cc: array<int, array{email: string, name: string}>,
     *     targetLabel: string,
     *     chain: array<int, string>
     * }
     */
    public function resolveRecipients(User $user, int $churchId): array
    {
        /** @var array<string, array{email: string, name: string}> $to */
        $to = [];
        /** @var array<string, array{email: string, name: string}> $cc */
        $cc = [];
        $chain = [];
        $senderEmail = $user->email;

        $person = fn (User $u): array => [
            'email' => $u->email,
            'name' => $u->name ?: $u->email,
        ];

        $managerOf = function (Church $church): ?User {
            return Church::with('manager:id,email,name,is_active')->find($church->id)?->manager;
        };

        /**
         * Rôles permettant d'occuper une fonction : un pasteur de sous-groupe
         * est aussi pasteur local, et un pasteur de groupe remplit les deux
         * fonctions inférieures.
         *
         * @return array<int, string>
         */
        $eligibleRoles = function (string $role): array {
            $wanted = RoleName::tryFrom($role);
            $names = [$role];

            if ($wanted) {
                foreach (RoleName::cases() as $candidate) {
                    if (in_array($wanted, $candidate->impliedRoles(), true)) {
                        $names[] = $candidate->value;
                    }
                }
            }

            return array_values(array_unique($names));
        };

        $hasEligibleRole = function (User $user, string $role) use ($eligibleRoles): bool {
            return collect($user->getRoleNames())
                ->intersect($eligibleRoles($role))
                ->isNotEmpty();
        };

        /** Le responsable de l'église est le destinataire prioritaire. */
        $managerFor = function (Church $church, string $role) use ($managerOf, $hasEligibleRole): ?User {
            $manager = $managerOf($church);

            if (! $manager || ! $manager->is_active || ! $hasEligibleRole($manager, $role)) {
                return null;
            }

            return $manager;
        };

        $activeRoleUsers = function (string $role, Church $church) use ($eligibleRoles): array {
            return User::whereHas('roles', fn ($query) => $query->whereIn('name', $eligibleRoles($role)))
                ->where('is_active', true)
                ->where('assigned_church_id', $church->id)
                ->get(['id', 'email', 'name'])
                ->all();
        };

        $pastorsFor = function (Church $church, string $role) use ($managerFor, $activeRoleUsers): array {
            $manager = $managerFor($church, $role);

            return $manager ? [$manager] : $activeRoleUsers($role, $church);
        };

        /** Toutes les églises sous-groupes situées sous une église groupe. */
        $subGroupChurches = function (Church $group): array {
            $subGroups = [];
            $visit = function (Church $parent) use (&$visit, &$subGroups): void {
                foreach (Church::where('parent_id', $parent->id)->get() as $child) {
                    if ($child->type === ChurchType::SUB_GROUP) {
                        $subGroups[$child->id] = $child;
                    }

                    $visit($child);
                }
            };
            $visit($group);

            return array_values($subGroups);
        };

        $addTo = function (User $manager, string $label) use (&$to, &$chain, $senderEmail, $person): void {
            if (! $manager->email || $manager->email === $senderEmail) {
                return;
            }

            $chain[] = $label;
            $to[$manager->email] = $person($manager);
        };

        $addCc = function (User $manager, string $label) use (&$cc, &$to, &$chain, $senderEmail, $person): void {
            if (! $manager->email || $manager->email === $senderEmail || isset($to[$manager->email])) {
                return;
            }

            $chain[] = $label.' (copie)';
            $cc[$manager->email] = $person($manager);
        };

        $addSuperAdmins = function (string $bucket) use (&$to, &$cc, &$chain, $senderEmail, $person): void {
            $chain[] = $bucket === 'to' ? 'super administrateurs' : 'super administrateurs (copie)';

            User::role(RoleName::SUPER_ADMIN->value)
                ->where('is_active', true)
                ->get(['id', 'email', 'name'])
                ->each(function (User $admin) use (&$to, &$cc, $bucket, $senderEmail, $person): void {
                    if (! $admin->email || $admin->email === $senderEmail) {
                        return;
                    }

                    if ($bucket === 'to' || ! isset($to[$admin->email])) {
                        ${$bucket}[$admin->email] = $person($admin);
                    }
                });
        };

        $church = Church::find($churchId);

        if (! $church) {
            throw new RuntimeException('Église introuvable');
        }

        $pastorRoles = [
            RoleName::LOCAL_PASTOR->value,
            RoleName::SUBGROUP_PASTOR->value,
            RoleName::GROUP_PASTOR->value,
            RoleName::SUPER_ADMIN->value,
        ];

        $isStaff = ! in_array($user->roleName(), $pastorRoles, true);

        $parent = $church->parent_id ? Church::find($church->parent_id) : null;
        $group = $church->type === ChurchType::GROUP ? $church : null;
        $cursor = $parent;

        while (! $group && $cursor) {
            if ($cursor->type === ChurchType::GROUP) {
                $group = $cursor;
                break;
            }

            $cursor = $cursor->parent_id ? Church::find($cursor->parent_id) : null;
        }

        // Le personnel envoie toujours son rapport au pasteur de SA église,
        // quel que soit le niveau : locale, sous-groupe ou groupe. Le niveau
        // supérieur et les super administrateurs sont en copie.
        if ($isStaff) {
            $staffTargets = [
                ChurchType::LOCAL->value => [RoleName::LOCAL_PASTOR, 'pasteur de votre église'],
                ChurchType::SUB_GROUP->value => [RoleName::SUBGROUP_PASTOR, 'pasteur de votre sous-groupe'],
                ChurchType::GROUP->value => [RoleName::GROUP_PASTOR, 'pasteur de votre groupe'],
            ];

            [$role, $targetLabel] = $staffTargets[$church->type->value];

            foreach ($pastorsFor($church, $role->value) as $pastor) {
                $addTo($pastor, "{$targetLabel} ({$church->name})");
            }

            if ($to === []) {
                throw new RuntimeException('Aucun pasteur actif n’est assigné à cette église.');
            }

            if ($church->type === ChurchType::LOCAL && $parent?->type === ChurchType::SUB_GROUP) {
                foreach ($pastorsFor($parent, RoleName::SUBGROUP_PASTOR->value) as $pastor) {
                    $addCc($pastor, "pasteur de sous-groupe ({$parent->name})");
                }
            }

            if ($church->type !== ChurchType::GROUP && $group) {
                foreach ($pastorsFor($group, RoleName::GROUP_PASTOR->value) as $pastor) {
                    $addCc($pastor, "pasteur de groupe ({$group->name})");
                }
            }
            $addSuperAdmins('cc');

            return [
                'recipients' => array_values($to),
                'cc' => array_values($cc),
                'targetLabel' => $targetLabel,
                'chain' => $chain,
            ];
        }

        // Une église groupe est le sommet de la chaîne locale pour un pasteur.
        if ($church->type === ChurchType::GROUP) {
            $addSuperAdmins('to');

            return [
                'recipients' => array_values($to),
                'cc' => [],
                'targetLabel' => 'hiérarchie nationale (super administrateurs)',
                'chain' => $chain,
            ];
        }

        $targetLabel = 'pasteur de votre groupe';

        // Une église locale remonte d'abord à son sous-groupe : c'est son
        // pasteur de sous-groupe qui reçoit le rapport, pas celui du groupe.
        if ($church->type === ChurchType::LOCAL && $parent?->type === ChurchType::SUB_GROUP) {
            $targetLabel = 'pasteur de votre sous-groupe';

            foreach ($pastorsFor($parent, RoleName::SUBGROUP_PASTOR->value) as $pastor) {
                $addTo($pastor, "pasteur de sous-groupe ({$parent->name})");
            }

            if ($to !== []) {
                if ($group) {
                    foreach ($pastorsFor($group, RoleName::GROUP_PASTOR->value) as $pastor) {
                        $addCc($pastor, "pasteur de groupe ({$group->name})");
                    }
                }
                $addSuperAdmins('cc');

                return [
                    'recipients' => array_values($to),
                    'cc' => array_values($cc),
                    'targetLabel' => $targetLabel,
                    'chain' => $chain,
                ];
            }

            // Aucun pasteur de sous-groupe actif : on remonte au groupe.
            $targetLabel = 'pasteur de votre groupe';
        }

        // Pasteur de sous-groupe (ou locale sans sous-groupe) : envoi au pasteur de groupe.
        if ($group) {
            foreach ($pastorsFor($group, RoleName::GROUP_PASTOR->value) as $pastor) {
                $addTo($pastor, "pasteur de groupe ({$group->name})");
            }
        }

        // Si l'expéditeur est lui-même le pasteur de groupe, on remonte à la
        // hiérarchie nationale plutôt que de s'envoyer le rapport.
        if ($to === []) {
            $addSuperAdmins('to');

            return [
                'recipients' => array_values($to),
                'cc' => [],
                'targetLabel' => 'hiérarchie nationale (super administrateurs)',
                'chain' => $chain,
            ];
        }

        // Un rapport de sous-groupe copie les autres sous-groupes du groupe.
        if ($church->type === ChurchType::SUB_GROUP) {
            foreach ($group ? $subGroupChurches($group) : [] as $subGroup) {
                foreach ($pastorsFor($subGroup, RoleName::SUBGROUP_PASTOR->value) as $pastor) {
                    $addCc($pastor, "pasteur de sous-groupe ({$subGroup->name})");
                }
            }
        }
        $addSuperAdmins('cc');

        return [
            'recipients' => array_values($to),
            'cc' => array_values($cc),
            'targetLabel' => $targetLabel,
            'chain' => $chain,
        ];
    }
}
