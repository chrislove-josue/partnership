<?php

namespace App\Filament\Resources\Cellules\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CellulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withTotalDonations())
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable(),
                TextColumn::make('church.name')
                    ->label('Église')
                    ->searchable(),
                TextColumn::make('leader.full_name')
                    ->label('Responsable')
                    ->placeholder('—'),
                TextColumn::make('partners_count')
                    ->label('Partenaires')
                    ->counts('partners')
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
                SelectFilter::make('church_id')
                    ->label('Église')
                    ->relationship('church', 'name')
                    ->searchable()
                    ->preload(),
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
