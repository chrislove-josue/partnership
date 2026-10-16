<?php

namespace App\Filament\Widgets;

use App\Services\ChurchAccessService;
use App\Services\DonationTrendService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

/**
 * Base des courbes de variation mensuelle des dons (une ligne par groupe).
 */
abstract class TrendChartWidget extends ChartWidget
{
    protected int|string|array $columnSpan = 'full';

    /**
     * @param  array<int, int>  $churchIds
     * @return array{labels: array<int, string>, series: array<int, array{label: string, data: array<int, float>}>}
     */
    abstract protected function trend(array $churchIds): array;

    protected function getData(): array
    {
        $actor = Auth::user();

        if (! $actor) {
            return ['datasets' => [], 'labels' => []];
        }

        $churchIds = app(ChurchAccessService::class)->accessibleChurchIds($actor);
        $trend = $this->trend($churchIds);

        $datasets = [];
        foreach ($trend['series'] as $index => $serie) {
            $color = DonationTrendService::PALETTE[$index % count(DonationTrendService::PALETTE)];

            $datasets[] = [
                'label' => $serie['label'],
                'data' => $serie['data'],
                'borderColor' => $color,
                'backgroundColor' => $color,
                'fill' => false,
                'tension' => 0.3,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $trend['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
