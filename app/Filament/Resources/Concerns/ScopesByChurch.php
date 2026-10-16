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

        $column = static::churchScopeColumn();

        if (str_contains($column, '.')) {
            [$relation, $relationColumn] = explode('.', $column, 2);

            return $query->whereHas($relation, fn (Builder $q) => $q->whereIn($relationColumn, $ids));
        }

        return $query->whereIn($column, $ids);
    }
}
