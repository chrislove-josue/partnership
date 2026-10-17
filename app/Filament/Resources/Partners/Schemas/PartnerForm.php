<?php

namespace App\Filament\Resources\Partners\Schemas;

use App\Services\ChurchAccessService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class PartnerForm
{
    public static function configure(Schema $schema): Schema
    {
        $access = app(ChurchAccessService::class);
        $actor = Auth::user();
        $churchIds = $actor ? $access->accessibleChurchIds($actor) : [];

        return $schema
            ->components([
                TextInput::make('first_name')
                    ->label('Prénom')
                    ->required()
                    ->maxLength(255),
                TextInput::make('last_name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Adresse email')
                    ->email()
                    ->unique(ignoreRecord: true),
                TextInput::make('phone')
                    ->label('Téléphone')
                    ->tel()
                    ->unique(ignoreRecord: true),
                TextInput::make('handle_kc')
                    ->label('Identifiant KingsChat (handle KC)')
                    ->unique(ignoreRecord: true),
                TextInput::make('address')
                    ->label('Adresse'),
                DatePicker::make('birthdate')
                    ->label('Date de naissance'),
                Select::make('church_id')
                    ->label('Église')
                    ->relationship('church', 'name', fn ($query) => $query->whereIn('id', $churchIds))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                Select::make('cellule_id')
                    ->label('Cellule')
                    ->relationship('cellule', 'name', fn ($query, $get) => $query
                        ->when($get('church_id'), fn ($q, $churchId) => $q->where('church_id', $churchId)))
                    ->searchable()
                    ->preload(),
            ]);
    }
}
