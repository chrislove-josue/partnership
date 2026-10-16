<?php

namespace App\Filament\Widgets;

use App\Services\DonationTrendService;

class DonationsByCelluleChart extends TrendChartWidget
{
    protected static ?int $sort = 5;

    public function getHeading(): ?string
    {
        return 'Variation des dons par cellule (top 10)';
    }

    protected function trend(array $churchIds): array
    {
        return app(DonationTrendService::class)->byCellule($churchIds, 10);
    }
}
