<?php

namespace App\Enums;

/** Port de PeriodType (lib/report.ts) : mois, trimestre ou année. */
enum PeriodType: string
{
    case MONTH = 'month';
    case QUARTER = 'quarter';
    case YEAR = 'year';

    public function label(): string
    {
        return match ($this) {
            self::MONTH => 'Par mois',
            self::QUARTER => 'Par trimestre',
            self::YEAR => 'Par année',
        };
    }
}
