<?php

namespace App\Filament\Resources\Donations;

use App\Filament\Resources\Concerns\ScopesByChurch;
use App\Filament\Resources\Donations\Pages\CreateDonation;
use App\Filament\Resources\Donations\Pages\EditDonation;
use App\Filament\Resources\Donations\Pages\ListDonations;
use App\Filament\Resources\Donations\Schemas\DonationForm;
use App\Filament\Resources\Donations\Tables\DonationsTable;
use App\Models\Donation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DonationResource extends Resource
{
    use ScopesByChurch;

    protected static ?string $model = Donation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Dons';

    protected static ?string $modelLabel = 'don';

    protected static ?string $pluralModelLabel = 'dons';

    protected static ?string $navigationLabel = 'Dons';

    public static function form(Schema $schema): Schema
    {
        return DonationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DonationsTable::configure($table);
    }

    public static function getGloballySearchableAttributes(): array
    {
        // `full_name` est un accesseur Eloquent : la recherche SQL doit porter
        // sur les colonnes réelles du partenaire.
        return ['partner.first_name', 'partner.last_name', 'church.name', 'category.name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Donation $record */
        return sprintf(
            '%s — %s XOF',
            $record->partner?->full_name ?? 'Partenaire inconnu',
            number_format((float) $record->amount, 0, ',', ' '),
        );
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Donation $record */
        return [
            'Église' => $record->church?->name ?? '—',
            'Catégorie' => $record->category?->name ?? '—',
            'Date' => $record->date?->format('d/m/Y') ?? '—',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDonations::route('/'),
            'create' => CreateDonation::route('/create'),
            'edit' => EditDonation::route('/{record}/edit'),
        ];
    }
}
