<?php

namespace App\Filament\Resources\Pastors\Tables;

use App\Enums\RoleName;
use App\Filament\Resources\Users\Tables\UsersTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PastorsTable
{
    public static function configure(Table $table): Table
    {
        return UsersTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                'roles',
                fn (Builder $query) => $query->whereIn('name', [
                    RoleName::GROUP_PASTOR->value,
                    RoleName::SUBGROUP_PASTOR->value,
                    RoleName::LOCAL_PASTOR->value,
                ]),
            ));
    }
}
