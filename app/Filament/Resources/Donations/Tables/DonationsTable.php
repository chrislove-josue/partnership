<?php

namespace App\Filament\Resources\Donations\Tables;

use App\Models\Donation;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DonationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Montant')
                    ->money('XOF', divideBy: 1)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('partner.full_name')
                    ->label('Partenaire')
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('church.name')
                    ->label('Église créditée')
                    ->searchable(),
                TextColumn::make('worshipChurch.name')
                    ->label('Église du culte')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('category.name')
                    ->label('Catégorie')
                    ->badge()
                    ->color(fn ($record) => $record->category?->color)
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('periode')
                    ->label('Période')
                    ->schema([
                        DatePicker::make('date_from')
                            ->label('Du'),
                        DatePicker::make('date_to')
                            ->label('Au'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['date_from'] ?? null, fn (Builder $q, $date) => $q->whereDate('date', '>=', $date))
                        ->when($data['date_to'] ?? null, fn (Builder $q, $date) => $q->whereDate('date', '<=', $date))),
                SelectFilter::make('is_active')
                    ->label('Statut')
                    ->options([
                        '1' => 'Actif',
                        '0' => 'Désactivé',
                    ])
                    ->default('1'),
                SelectFilter::make('church_id')
                    ->label('Église')
                    ->relationship('church', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('category_id')
                    ->label('Catégorie')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('partner_id')
                    ->label('Partenaire')
                    ->relationship('partner', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->searchable(['first_name', 'last_name'])
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('toggleActive')
                    ->label(fn (Donation $record): string => $record->is_active ? 'Désactiver' : 'Réactiver')
                    ->icon(fn (Donation $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Donation $record): string => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Donation $record): string => $record->is_active ? 'Désactiver ce don ?' : 'Réactiver ce don ?')
                    ->modalDescription(fn (Donation $record): string => $record->is_active
                        ? 'Le don sera conservé mais exclu des rapports et des totaux.'
                        : 'Le don sera de nouveau pris en compte dans les rapports.')
                    ->action(fn (Donation $record) => $record->update(['is_active' => ! $record->is_active])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
