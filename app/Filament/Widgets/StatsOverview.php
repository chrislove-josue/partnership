<?php

namespace App\Filament\Widgets;

use App\Enums\RoleName;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Partner;
use App\Services\ChurchAccessService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $actor = Auth::user();

        if (! $actor) {
            return [];
        }

        $access = app(ChurchAccessService::class);
        $churchIds = array_map('intval', $access->accessibleChurchIds($actor));

        $donationsQuery = Donation::query()
            ->whereIn('church_id', $churchIds)
            ->where('is_active', true);
        $partnersQuery = Partner::query()->whereIn('church_id', $churchIds);

        $totalAmount = (clone $donationsQuery)->sum('amount');
        $startOfMonth = now()->startOfMonth();
        $monthAmount = (clone $donationsQuery)->where('date', '>=', $startOfMonth)->sum('amount');
        $donationsCount = (clone $donationsQuery)->count();
        $partnersCount = $partnersQuery->count();

        $currency = ' FCFA';
        $formatted = fn ($amount): string => number_format((float) $amount, 0, ',', ' ').$currency;

        // Les pasteurs de groupe/sous-groupe voient le total de leur église
        // affectée et celui de l'église dont ils sont responsables.
        $role = $actor->roleName();
        $showBothTotals = in_array($role, [
            RoleName::GROUP_PASTOR->value,
            RoleName::SUBGROUP_PASTOR->value,
        ], true);

        $stats = [];

        if ($showBothTotals) {
            $assignedChurch = $actor->assignedChurch;
            $ownIds = $assignedChurch
                ? array_merge([$assignedChurch->id], $assignedChurch->descendantIds())
                : [];

            // Le total "de votre église" régroupe les dons de toute sa descendance
            // (une église de groupe/sous-groupe ne reçoit pas de dons directs).
            $ownQuery = Donation::query()
                ->whereIn('church_id', $ownIds)
                ->where('is_active', true);
            $ownAmount = $ownQuery->sum('amount');
            $ownCount = $ownQuery->count();

            $stats[] = Stat::make(
                'Dons de votre église'.($assignedChurch ? ' — '.$assignedChurch->name : ''),
                $formatted($ownAmount),
            )
                ->description($ownCount.' don(s) dans votre église et ses filles')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('primary');

            // L'église dont le pasteur est responsable (église mère) : ses
            // propres dons, sans ceux de ses filles.
            $managedChurch = Church::query()->where('manager_id', $actor->id)->first();

            if ($managedChurch) {
                $managedQuery = Donation::query()
                    ->where('church_id', $managedChurch->id)
                    ->where('is_active', true);
                $managedAmount = $managedQuery->sum('amount');
                $managedCount = $managedQuery->count();

                $stats[] = Stat::make('Dons de votre église — '.$managedChurch->name, $formatted($managedAmount))
                    ->description($managedCount.' don(s) dans cette église')
                    ->descriptionIcon('heroicon-o-building-office-2')
                    ->color('warning');
            }
        } else {
            $stats[] = Stat::make('Dons totaux', $formatted($totalAmount))
                ->description($donationsCount.' don(s) enregistré(s)')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success');
        }

        $stats[] = Stat::make('Dons ce mois', $formatted($monthAmount))
            ->description('Depuis le '.$startOfMonth->format('d/m/Y'))
            ->descriptionIcon('heroicon-m-calendar')
            ->color('info');

        $stats[] = Stat::make('Partenaires', number_format($partnersCount, 0, ',', ' '))
            ->description('Sur votre périmètre')
            ->descriptionIcon('heroicon-m-user-group')
            ->color('warning');

        $stats[] = Stat::make('Églises accessibles', count($churchIds))
            ->description('Périmètre de gestion')
            ->descriptionIcon('heroicon-m-building-office')
            ->color('primary');

        return $stats;
    }
}
