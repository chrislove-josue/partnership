<?php

namespace App\Filament\Resources\Churches\Tables;

use App\Enums\ChurchType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
