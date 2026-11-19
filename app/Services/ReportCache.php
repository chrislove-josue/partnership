<?php

namespace App\Services;

use App\Enums\PeriodType;
use Illuminate\Support\Facades\Cache;

/**
 * Cache des rapports de partenariat.
 *
 * La clé contient une version globale incrémentée à chaque écriture sur les
 * données du rapport : une modification est donc visible immédiatement, sans
 * attendre l'expiration du cache.
 */
class ReportCache
{
    private const VERSION_KEY = 'partnership-report:version';

    /**
     * @param  callable(): array<string, mixed>  $builder
     * @return array<string, mixed>
     */
    public function remember(
        int $churchId,
        PeriodType $type,
        int $year,
        ?int $index,
        callable $builder,
    ): array {
        return Cache::remember(
            $this->key($churchId, $type, $year, $index),
            now()->addSeconds((int) config('cache.report_ttl', 300)),
            $builder,
        );
    }

    /** Invalide tous les rapports après une modification de leurs données. */
    public function invalidate(): void
    {
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 0) + 1);
    }

    private function key(int $churchId, PeriodType $type, int $year, ?int $index): string
    {
        $version = (int) Cache::get(self::VERSION_KEY, 0);

        return sprintf(
            'partnership-report:v%d:%d:%s:%d:%s',
            $version,
            $churchId,
            $type->value,
            $year,
            $index ?? 'all',
        );
    }
}
