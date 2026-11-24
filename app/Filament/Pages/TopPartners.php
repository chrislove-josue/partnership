<?php

namespace App\Filament\Pages;

use App\Enums\PeriodType;
use App\Services\ChurchAccessService;
use App\Services\TopPartnerService;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** Classement des partenaires par montant donné sur une période. */
class TopPartners extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Top partenaires';

    protected static string|\UnitEnum|null $navigationGroup = 'Rapports';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Top partenaires';

    protected string $view = 'filament.pages.top-partners';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'period' => PeriodType::MONTH->value,
            'month' => (int) now()->month,
            'quarter' => (int) now()->quarter,
            'year' => (int) now()->year,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $months = collect([
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
            7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ])->all();

        return $schema
            ->components([
                Select::make('period')->selectablePlaceholder(false)
                    ->label('Période')
                    ->options(collect(PeriodType::cases())->mapWithKeys(
                        fn (PeriodType $type): array => [$type->value => $type->label()],
                    )->all())
                    ->required()
                    ->live(),

                Select::make('month')->selectablePlaceholder(false)
                    ->label('Mois')
                    ->options($months)
                    ->visible(fn (Get $get): bool => $get('period') === PeriodType::MONTH->value)
                    ->required()
                    ->live(),

                Select::make('quarter')->selectablePlaceholder(false)
                    ->label('Trimestre')
                    ->options([1 => 'T1', 2 => 'T2', 3 => 'T3', 4 => 'T4'])
                    ->visible(fn (Get $get): bool => $get('period') === PeriodType::QUARTER->value)
                    ->required()
                    ->live(),

                Select::make('year')->selectablePlaceholder(false)
                    ->label('Année')
                    ->options(collect(range((int) now()->year - 3, (int) now()->year + 1))
                        ->mapWithKeys(fn (int $year): array => [$year => (string) $year])
                        ->all())
                    ->required()
                    ->live(),
            ])
            ->columns(4)
            ->statePath('data')
            ->live();
    }

    /** @return array<string, mixed> */
    public function getRanking(): array
    {
        $state = is_array($this->data) ? $this->data : [];

        $period = PeriodType::tryFrom((string) ($state['period'] ?? '')) ?? PeriodType::MONTH;
        $year = (int) ($state['year'] ?? now()->year);
        $index = match ($period) {
            PeriodType::MONTH => (int) ($state['month'] ?? now()->month),
            PeriodType::QUARTER => (int) ($state['quarter'] ?? now()->quarter),
            PeriodType::YEAR => 1,
        };

        $actor = Auth::user();

        try {
            $service = app(TopPartnerService::class);
            $items = $service->rank(
                $actor ? app(ChurchAccessService::class)->accessibleChurchIds($actor) : [],
                $period,
                $year,
                $index,
            );

            [$from, $to] = $service->range($period, $year, $index);

            return [
                'items' => $items,
                'label' => Str::ucfirst($period->label()),
                'range' => $from->format('d/m/Y').' – '.$to->format('d/m/Y'),
            ];
        } catch (\Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }
    }
}
