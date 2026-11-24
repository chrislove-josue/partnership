<?php

namespace App\Filament\Resources\Donations\Schemas;

use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Partner;
use App\Services\ChurchAccessService;
use App\Services\DonationAttributionService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
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
                Select::make('worship_church_id')->selectablePlaceholder(false)
                    ->label('Église du culte')
                    ->relationship('worshipChurch', 'name', fn ($query) => $query->whereIn('id', $churchIds))
                    ->default(fn (): ?int => $access->defaultChurchId($actor))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                Select::make('partner_id')->selectablePlaceholder(false)
                    ->label('Partenaire')
                    ->relationship('partner', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->searchable(['first_name', 'last_name'])
                    ->preload()
                    ->required()
                    ->helperText('Tous les partenaires sont disponibles, quelle que soit leur église d’origine.'),
                Select::make('category_id')->selectablePlaceholder(false)
                    ->label('Catégorie')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->helperText(function (Get $get): string {
                        $partner = Partner::find($get('partner_id'));
                        $worship = Church::find($get('worship_church_id'));
                        $category = CategoryDon::find($get('category_id'));

                        if (! $partner || ! $worship || ! $category) {
                            return 'L’église créditée sera déterminée selon la famille de la catégorie.';
                        }

                        $attribution = app(DonationAttributionService::class)
                            ->churchFor($partner, $category, $worship);

                        return 'Ce don sera crédité à : '.$attribution->name;
                    }),
            ]);
    }
}
