<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Enums\RoleName;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => RoleName::tryFrom($state)?->label() ?? $state)
                    ->searchable(),
                TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->counts('permissions')
                    ->badge(),
                TextColumn::make('users_count')
                    ->label('Utilisateurs')
                    ->counts('users')
                    ->badge(),
                IconColumn::make('is_system')
                    ->label('Système')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
