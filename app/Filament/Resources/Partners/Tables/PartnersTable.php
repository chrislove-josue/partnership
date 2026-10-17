<?php

namespace App\Filament\Resources\Partners\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PartnersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withTotalDonations())
            ->columns([
                TextColumn::make('full_name')
                    ->label('Nom complet')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['last_name']),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->label('Téléphone')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('handle_kc')
                    ->label('KingsChat')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('church.name')
                    ->label('Église')
                    ->searchable(),
                TextColumn::make('cellule.name')
                    ->label('Cellule')
                    ->placeholder('—'),
                TextColumn::make('total_donations')
                    ->label('Montant total')
                    ->money('XOF')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('birthdate')
                    ->label('Naissance')
                    ->date('d/m/Y'),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y'),
            ])
            ->filters([
                SelectFilter::make('church_id')
                    ->label('Église')
                    ->relationship('church', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('cellule_id')
                    ->label('Cellule')
                    ->relationship('cellule', 'name')
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
