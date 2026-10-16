<?php

namespace App\Filament\Widgets;

use App\Models\Donation;
use App\Models\Partner;
use App\Services\ChurchAccessService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $actor = Auth::user();

        if (! $actor) {
            return [];
        }

        $churchIds = app(ChurchAccessService::class)->accessibleChurchIds($actor);

        $donationsQuery = Donation::query()->whereIn('church_id', $churchIds);
        $partnersQuery = Partner::query()->whereIn('church_id', $churchIds);

        $totalAmount = (clone $donationsQuery)->sum('amount');
        $startOfMonth = now()->startOfMonth();
        $monthAmount = (clone $donationsQuery)->where('date', '>=', $startOfMonth)->sum('amount');
        $donationsCount = (clone $donationsQuery)->count();
        $partnersCount = $partnersQuery->count();

        $currency = ' FCFA';

        return [
            Stat::make('Dons totaux', number_format((float) $totalAmount, 0, ',', ' ').$currency)
                ->description($donationsCount.' don(s) enregistré(s)')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Dons ce mois', number_format((float) $monthAmount, 0, ',', ' ').$currency)
                ->description('Depuis le '.$startOfMonth->format('d/m/Y'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),
            Stat::make('Partenaires', number_format($partnersCount, 0, ',', ' '))
                ->description('Sur votre périmètre')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('warning'),
            Stat::make('Églises accessibles', count($churchIds))
                ->description('Périmètre de gestion')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('primary'),
        ];
    }
}
