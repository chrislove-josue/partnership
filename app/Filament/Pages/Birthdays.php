<?php

namespace App\Filament\Pages;

use App\Services\BirthdayService;
use App\Services\ChurchAccessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** Liste des anniversaires du mois pour les églises accessibles. */
class Birthdays extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Anniversaires des partenaires';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Anniversaires des partenaires';

    protected string $view = 'filament.pages.birthdays';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'month' => (int) now()->month,
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
            ])
            ->columns(1)
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
        $year = (int) now()->year;

        $user = Auth::user();

        try {
            $items = app(BirthdayService::class)->monthBirthdays(
                $month,
                $year,
                $user ? app(ChurchAccessService::class)->accessibleChurchIds($user) : [],
            );

            $label = Str::ucfirst(Carbon::create($year, $month, 1)->locale('fr')->isoFormat('MMMM'));

            return [
                'label' => $label,
                'count' => $items->count(),
                'items' => $items,
            ];
        } catch (\Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportPdf')
                ->label('Télécharger en PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->action(function () {
                    $state = is_array($this->data) ? $this->data : [];
                    $month = (int) ($state['month'] ?? now()->month);
                    $year = (int) now()->year;

                    $user = Auth::user();
                    $items = app(BirthdayService::class)->monthBirthdays(
                        $month,
                        $year,
                        $user ? app(ChurchAccessService::class)->accessibleChurchIds($user) : [],
                    );

                    $label = Str::ucfirst(Carbon::create($year, $month, 1)->locale('fr')->isoFormat('MMMM'));

                    $pdf = Pdf::loadView('pdf.birthdays', [
                        'items' => $items,
                        'label' => $label,
                        'count' => $items->count(),
                    ])->setPaper('a4', 'portrait');

                    $filename = sprintf('anniversaires_%02d.pdf', $month);

                    return response()->streamDownload(
                        fn () => print $pdf->output(),
                        $filename,
                        ['Content-Type' => 'application/pdf'],
                    );
                }),
        ];
    }
}
