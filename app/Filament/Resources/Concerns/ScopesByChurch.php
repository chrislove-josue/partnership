<?php

namespace App\Filament\Resources\Concerns;

use App\Services\ChurchAccessService;
use Illuminate\Database\Eloquent\Builder;

trait ScopesByChurch
{
    /**
     * Colonne utilisée pour filtrer par église. À surcharger si besoin.
     */
    public static function churchScopeColumn(): string
    {
        return 'church_id';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if (! $user) {
            return $query;
        }

        $ids = app(ChurchAccessService::class)->accessibleChurchIds($user);

        return $query->whereIn(static::churchScopeColumn(), $ids);
    }
}
