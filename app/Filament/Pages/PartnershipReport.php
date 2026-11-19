<?php

namespace App\Filament\Pages;

use App\Enums\PeriodType;
use App\Services\ChurchAccessService;
use App\Services\PartnershipReportExporter;
use App\Services\PartnershipReportSender;
use App\Services\PartnershipReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Throwable;

/** Port de app/dashboard/reports/partnership/page.tsx. */
class PartnershipReport extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Rapport de partenariat';

    protected static string|\UnitEnum|null $navigationGroup = 'Rapports';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Rapport de partenariat';

    protected string $view = 'filament.pages.partnership-report';

    /** @var array<string, mixed> | null */
    public ?array $data = [];

    public function mount(): void
    {
        $user = Auth::user();
        $churches = $user
            ? app(ChurchAccessService::class)->accessibleChurches($user)
            : collect();

        $this->form->fill([
            'church_id' => $user?->assigned_church_id ?? $churches->first()?->id,
            'type' => PeriodType::MONTH->value,
            'month' => now()->month,
            'quarter' => now()->quarter,
            'year' => now()->year,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $user = Auth::user();
        $churches = $user
            ? app(ChurchAccessService::class)->accessibleChurches($user)
            : collect();

        $months = collect([
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
            7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ])->all();

        $periods = collect(PeriodType::cases())
            ->mapWithKeys(fn (PeriodType $type): array => [$type->value => $type->label()])
            ->all();

        return $schema
            ->components([
                Select::make('church_id')
                    ->label('Église')
                    ->options($churches->pluck('name', 'id')->all())
                    ->searchable()
                    ->required()
                    ->live(),

                Select::make('type')
                    ->label('Période')
                    ->options($periods)
                    ->required()
                    ->live(),

                Select::make('month')
                    ->label('Mois')
                    ->options($months)
                    ->visible(fn (Get $get): bool => $get('type') === PeriodType::MONTH->value)
                    ->live(),

                Select::make('quarter')
                    ->label('Trimestre')
                    ->options([1 => 'T1', 2 => 'T2', 3 => 'T3', 4 => 'T4'])
                    ->visible(fn (Get $get): bool => $get('type') === PeriodType::QUARTER->value)
                    ->live(),

                Select::make('year')
                    ->label('Année')
                    ->options(collect(range((int) now()->year - 5, (int) now()->year + 1))
                        ->mapWithKeys(fn (int $year): array => [$year => (string) $year])
                        ->all())
                    ->required()
                    ->live(),
            ])
            ->columns(5)
            ->statePath('data')
            ->live();
    }

    /**
     * Rapport affiché dans la vue, recalculé à chaque changement du formulaire.
     *
     * @return array<string, mixed>
     */
    public function getReport(): array
    {
        $state = $this->form->getState();

        if (empty($state['church_id'])) {
            return ['error' => 'Aucune église sélectionnée.'];
        }

        try {
            $user = Auth::user();
            $churchId = (int) $state['church_id'];

            if ($user && ! app(ChurchAccessService::class)->canAccessChurchData($user, $churchId)) {
                return ['error' => 'Accès refusé à cette église.'];
            }

            [$type, $year, $index] = $this->periodFromState($state);

            return app(PartnershipReportService::class)->buildReport($churchId, $type, $year, $index);
        } catch (Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Exporter en Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->action(function () {
                    try {
                        [$user, $churchId, $type, $year, $index] = $this->readSelection();

                        if (! app(ChurchAccessService::class)->canAccessChurchData($user, $churchId)) {
                            Notification::make()->title('Accès refusé à cette église')->danger()->send();

                            return;
                        }

                        $reports = app(PartnershipReportService::class);
                        $report = $reports->buildReport($churchId, $type, $year, $index);
                        $xlsx = app(PartnershipReportExporter::class)->toXlsx($report);

                        return response()->streamDownload(
                            fn () => print $xlsx,
                            $reports->fileName($report),
                            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                        );
                    } catch (Throwable $exception) {
                        Notification::make()->title('Échec de la génération du fichier')->body($exception->getMessage())->danger()->send();
                    }
                }),

            Action::make('send')
                ->label('Envoyer à la hiérarchie')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Envoyer le rapport à la hiérarchie ?')
                ->modalDescription(fn (): string => $this->sendModalDescription())
                ->modalSubmitActionLabel('Confirmer l’envoi')
                ->modalCancelActionLabel('Annuler')
                ->schema(fn (): ?array => $this->sendCcSchema())
                ->action(function (array $data): void {
                    try {
                        [$user, $churchId, $type, $year, $index] = $this->readSelection();

                        $result = app(PartnershipReportSender::class)
                            ->send(
                                $user,
                                $churchId,
                                $type,
                                $year,
                                $index,
                                true,
                                $data['cc_emails'] ?? null,
                            );

                        $body = count($result['to']).' destinataire(s) — '.$result['targetLabel'];

                        if (($result['cc'] ?? []) !== []) {
                            $body .= ' · '.count($result['cc']).' en copie';
                        }

                        Notification::make()
                            ->title('Rapport envoyé')
                            ->body($body)
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()->title('Échec de l’envoi du rapport')->body($exception->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    /** @return array{0: \App\Models\User, 1: int, 2: PeriodType, 3: int, 4: int|null} */
    private function readSelection(): array
    {
        $state = $this->form->getState();
        $user = Auth::user();

        if (! $user) {
            throw new \RuntimeException('Utilisateur non authentifié');
        }
        if (empty($state['church_id'])) {
            throw new \RuntimeException('Aucune église sélectionnée');
        }

        [$type, $year, $index] = $this->periodFromState($state);

        return [$user, (int) $state['church_id'], $type, $year, $index];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{0: PeriodType, 1: int, 2: int|null}
     */
    private function periodFromState(array $state): array
    {
        $type = PeriodType::tryFrom((string) ($state['type'] ?? '')) ?? PeriodType::MONTH;
        $year = (int) ($state['year'] ?? now()->year);
        $index = match ($type) {
            PeriodType::MONTH => (int) ($state['month'] ?? 1),
            PeriodType::QUARTER => (int) ($state['quarter'] ?? 1),
            PeriodType::YEAR => null,
        };

        return [$type, $year, $index];
    }

    /** Description du dialogue de confirmation : church, période, total et destinataires. */
    private function sendModalDescription(): string
    {
        try {
            [$user, $churchId, $type, $year, $index] = $this->readSelection();
            $preview = app(PartnershipReportSender::class)
                ->preview($user, $churchId, $type, $year, $index);

            $reports = app(PartnershipReportService::class);
            $total = $reports->formatAmount($preview['totals']['amount']);
            $emails = implode(', ', array_column($preview['recipients'], 'email'));
            $description = $preview['church']['name'].' · '.$preview['period']['label']
                .' · Total : '.$total.' sur '.$preview['totals']['donationCount'].' don(s)'
                .' · À ('.$preview['targetLabel'].') : '.$emails;

            if (($preview['cc'] ?? []) !== []) {
                $description .= ' · Copie : '.implode(', ', array_column($preview['cc'], 'email'));
            }

            return $description;
        } catch (Throwable $exception) {
            return $exception->getMessage();
        }
    }
}
