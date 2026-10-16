<?php

namespace App\Filament\Concerns;

use App\Services\ChurchAccessService;
use Illuminate\Database\Eloquent\Builder;

trait ScopesByChurch
{
    /**
     * Colonne utilisée pour filtrer (church_id par défaut).
     * Les sous-classes peuvent la surcharger (ex: assigned_church_id pour User).
     */
    protected static string $churchScopeColumn = 'church_id';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if (! $user) {
            return $query;
        }

        $ids = app(ChurchAccessService::class)->accessibleChurchIds($user);

        return $query->whereIn(static::$churchColumn, $ids);
    }

    protected static string $churchColumn = 'church_id';
}
