<?php

namespace App\Filament\Resources\Churches\Tables;

use App\Enums\ChurchType;
use App\Filament\Resources\Churches\Actions\ChurchDeleteGuard;
use App\Filament\Resources\Churches\Actions\ChurchStatusActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChurchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withTotalDonations())
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (ChurchType $state) => $state->label())
                    ->color(fn (ChurchType $state) => match ($state) {
                        ChurchType::GROUP => 'success',
                        ChurchType::SUB_GROUP => 'warning',
                        ChurchType::LOCAL => 'info',
                    }),
                TextColumn::make('parent.name')
                    ->label('Église parente')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('manager.name')
                    ->label('Responsable')
                    ->searchable()
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('agents_count')
                    ->label('Utilisateurs')
                    ->counts('agents')
                    ->badge(),
                TextColumn::make('total_donations')
                    ->label('Montant total')
                    ->money('XOF')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(collect(ChurchType::cases())->mapWithKeys(
                        fn (ChurchType $type) => [$type->value => $type->label()]
                    )->all()),
                TernaryFilter::make('is_active')
                    ->label('Statut')
                    ->trueLabel('Actives')
                    ->falseLabel('Inactives')
                    ->nullableLabel('Toutes'),
            ])
            ->recordActions([
                EditAction::make(),
                ChurchStatusActions::deactivate(),
                ChurchStatusActions::activate(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(fn (DeleteBulkAction $action) => ChurchDeleteGuard::beforeBulkDelete($action)),
                ]),
            ]);
    }
}
