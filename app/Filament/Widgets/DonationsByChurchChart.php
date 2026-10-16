<?php

namespace App\Filament\Widgets;

use App\Services\DonationTrendService;

class DonationsByChurchChart extends TrendChartWidget
{
    protected static ?int $sort = 5;

    public function getHeading(): ?string
    {
        return 'Variation des dons par église';
    }

    protected function trend(array $churchIds): array
    {
        return app(DonationTrendService::class)->byChurch($churchIds);
    }
}
