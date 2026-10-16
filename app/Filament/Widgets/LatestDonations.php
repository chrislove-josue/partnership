<?php

namespace App\Filament\Widgets;

use App\Models\Donation;
use App\Services\ChurchAccessService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class LatestDonations extends TableWidget
{
    protected static ?string $heading = 'Derniers dons';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $actor = Auth::user();
                $churchIds = $actor
                    ? app(ChurchAccessService::class)->accessibleChurchIds($actor)
                    : [];

                return Donation::query()
                    ->whereIn('church_id', $churchIds)
                    ->with(['partner', 'category', 'church'])
                    ->latest('date');
            })
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->date('d/m/Y'),
                TextColumn::make('partner.full_name')
                    ->label('Partenaire'),
                TextColumn::make('church.name')
                    ->label('Église'),
                TextColumn::make('category.name')
                    ->label('Catégorie')
                    ->badge(),
                TextColumn::make('amount')
                    ->label('Montant')
                    ->money('XOF')
                    ->alignEnd(),
            ])
            ->paginated([5])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
