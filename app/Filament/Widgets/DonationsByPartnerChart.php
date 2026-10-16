<?php

namespace App\Filament\Widgets;

use App\Services\DonationTrendService;

class DonationsByPartnerChart extends TrendChartWidget
{
    protected static ?int $sort = 3;

    public function getHeading(): ?string
    {
        return 'Variation des dons par partenaire (top 10)';
    }

    protected function trend(array $churchIds): array
    {
        return app(DonationTrendService::class)->byPartner($churchIds, 10);
    }
}
