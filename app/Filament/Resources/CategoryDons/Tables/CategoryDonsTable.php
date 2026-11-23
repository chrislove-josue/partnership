<?php

namespace App\Filament\Resources\CategoryDons\Tables;

use App\Enums\CategoryGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CategoryDonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('color')
                    ->label('Couleur'),
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable(),
                TextColumn::make('group')
                    ->label('Famille')
                    ->badge()
                    ->formatStateUsing(fn (?CategoryGroup $state): string => $state?->label() ?? '—'),
                TextColumn::make('donations_count')
                    ->label('Dons')
                    ->counts('donations')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('group')
                    ->label('Famille')
                    ->options(collect(CategoryGroup::cases())->mapWithKeys(
                        fn (CategoryGroup $group): array => [$group->value => $group->label()],
                    )->all()),
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
