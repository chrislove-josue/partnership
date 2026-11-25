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

        // Les pasteurs de groupe/sous-groupe voient d'abord le total de leur
        // église affectée, puis le total de toutes les églises accessibles.
        $role = $actor->roleName();
        $showBothTotals = in_array($role, [
            RoleName::GROUP_PASTOR->value,
            RoleName::SUBGROUP_PASTOR->value,
        ], true);

        $stats = [];

        if ($showBothTotals) {
            $assignedChurchId = (int) ($actor->assigned_church_id ?? 0);
            $assignedChurch = Church::query()->find($assignedChurchId);

            $ownQuery = Donation::query()
                ->where('church_id', $assignedChurchId)
                ->where('is_active', true);
            $ownAmount = $ownQuery->sum('amount');
            $ownCount = $ownQuery->count();

            $stats[] = Stat::make(
                'Dons de votre église'.($assignedChurch ? ' — '.$assignedChurch->name : ''),
                $formatted($ownAmount),
            )
                ->description($ownCount.' don(s) dans votre église')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('primary');

            $stats[] = Stat::make('Dons sur toutes les églises accessibles', $formatted($totalAmount))
                ->description($donationsCount.' don(s) sur toutes les églises accessibles')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success');
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
