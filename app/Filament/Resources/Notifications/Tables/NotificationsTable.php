<?php

namespace App\Filament\Resources\Notifications\Tables;

use App\Enums\NotificationType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class NotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (NotificationType $state) => $state->label())
                    ->color(fn (NotificationType $state) => match ($state) {
                        NotificationType::NEW_DONATION => 'success',
                        NotificationType::NEW_PARTNER => 'info',
                        NotificationType::SYSTEM => 'gray',
                    }),
                TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('user.name')
                    ->label('Destinataire')
                    ->searchable(),
                IconColumn::make('is_read')
                    ->label('Lue')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Reçue le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(NotificationType::class),
                SelectFilter::make('is_read')
                    ->label('Statut')
                    ->options([
                        '1' => 'Lue',
                        '0' => 'Non lue',
                    ]),
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
