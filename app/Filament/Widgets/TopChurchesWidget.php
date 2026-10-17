<?php

namespace App\Filament\Widgets;

use App\Enums\ChurchType;
use App\Models\Church;
use App\Services\ChurchAccessService;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TopChurchesWidget extends TableWidget
{
    protected ?string $heading = 'Top 5 des églises partenaires';

    protected function getTableHeaderActions(): array
    {
        return [];
    }

    protected function getHeading(): string
    {
        return 'Top 5 des églises partenaires';
    }

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $actor = Auth::user();

                if (! $actor) {
                    return Church::query()->whereRaw('1 = 0');
                }

                $ids = app(ChurchAccessService::class)->accessibleChurchIds($actor);

                return Church::query()
                    ->whereIn('id', $ids)
                    ->withTotalDonations()
                    ->orderByDesc('total_donations')
                    ->limit(5);
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Église'),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (ChurchType $state) => $state->label())
                    ->color(fn (ChurchType $state) => match ($state) {
                        ChurchType::GROUP => 'success',
                        ChurchType::SUB_GROUP => 'warning',
                        ChurchType::LOCAL => 'info',
                    }),
                TextColumn::make('partners_count')
                    ->label('Partenaires')
                    ->counts('partners')
                    ->badge(),
                TextColumn::make('total_donations')
                    ->label('Montant total')
                    ->money('XOF')
                    ->alignEnd(),
            ])
            ->paginated(false)
            ->recordActions([])
            ->toolbarActions([]);
    }
}
