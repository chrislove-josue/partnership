<?php

namespace App\Filament\Resources\Donations\Schemas;

use App\Services\ChurchAccessService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class DonationForm
{
    public static function configure(Schema $schema): Schema
    {
        $access = app(ChurchAccessService::class);
        $actor = Auth::user();
        $churchIds = $actor ? $access->accessibleChurchIds($actor) : [];

        return $schema
            ->components([
                TextInput::make('amount')
                    ->label('Montant')
                    ->required()
                    ->numeric()
                    ->prefix('FCFA'),
                DateTimePicker::make('date')
                    ->label('Date')
                    ->required()
                    ->default(now()),
                Select::make('church_id')
                    ->label('Église')
                    ->relationship('church', 'name', fn ($query) => $query->whereIn('id', $churchIds))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                Select::make('partner_id')
                    ->label('Partenaire')
                    ->relationship('partner', 'first_name', fn ($query, $get) => $query
                        ->when($get('church_id'), fn ($q, $churchId) => $q->where('church_id', $churchId))
                        ->whereIn('church_id', $churchIds))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->searchable(['first_name', 'last_name'])
                    ->preload()
                    ->required(),
                Select::make('category_id')
                    ->label('Catégorie')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
            ]);
    }
}
