<?php

namespace App\Filament\Widgets;

use App\Models\Donation;
use App\Services\ChurchAccessService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class DonationsChart extends ChartWidget
{
    protected static ?string $heading = 'Dons des 12 derniers mois';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $actor = Auth::user();

        if (! $actor) {
            return ['datasets' => [], 'labels' => []];
        }

        $churchIds = app(ChurchAccessService::class)->accessibleChurchIds($actor);

        $labels = [];
        $values = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $labels[] = ucfirst($month->translatedFormat('M Y'));

            $values[] = (float) Donation::query()
                ->whereIn('church_id', $churchIds)
                ->whereYear('date', $month->year)
                ->whereMonth('date', $month->month)
                ->sum('amount');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Montant (FCFA)',
                    'data' => $values,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
