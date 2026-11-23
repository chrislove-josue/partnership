<?php

namespace App\Filament\Pages;

use App\Services\BirthdayService;
use App\Services\ChurchAccessService;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/** Liste des anniversaires du mois pour les églises accessibles. */
class Birthdays extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Anniversaires';

    protected static string|\UnitEnum|null $navigationGroup = 'Structure';

    protected static ?int $navigationSort = 40;

    protected static ?string $title = 'Anniversaires du mois';

    protected string $view = 'filament.pages.birthdays';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'month' => (int) now()->month,
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
                Select::make('month')->selectablePlaceholder(false)
                    ->label('Mois')
                    ->options($months)
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
            ->columns(2)
            ->statePath('data')
            ->live();
    }

    /**
     * Anniversaires du mois sélectionné, recalculés à chaque changement.
     *
     * @return array<string, mixed>
     */
    public function getBirthdays(): array
    {
        $state = is_array($this->data) ? $this->data : [];

        $month = (int) ($state['month'] ?? now()->month);
        $year = (int) ($state['year'] ?? now()->year);

        $user = Auth::user();

        try {
            $items = app(BirthdayService::class)->monthBirthdays(
                $month,
                $year,
                $user ? app(ChurchAccessService::class)->accessibleChurchIds($user) : [],
            );

            $label = \Illuminate\Support\Carbon::create($year, $month, 1)
                ->locale('fr')
                ->isoFormat('MMMM YYYY');

            return [
                'label' => \Illuminate\Support\Str::ucfirst($label),
                'count' => $items->count(),
                'items' => $items,
            ];
        } catch (\Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }
    }
}
