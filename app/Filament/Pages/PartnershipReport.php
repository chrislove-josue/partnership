<?php

namespace App\Filament\Pages;

use App\Enums\CategoryGroup;
use App\Enums\PeriodType;
use App\Models\User;
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
            'report_section' => '',
            'category_group' => '',
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
                Select::make('church_id')->selectablePlaceholder(false)
                    ->label('Église')
                    ->options($churches->pluck('name', 'id')->all())
                    ->searchable()
                    ->required()
                    ->live(),

                Select::make('type')->selectablePlaceholder(false)
                    ->label('Période')
                    ->options($periods)
                    ->required()
                    ->live(),

                Select::make('month')->selectablePlaceholder(false)
                    ->label('Mois')
                    ->options($months)
                    ->visible(fn (Get $get): bool => $get('type') === PeriodType::MONTH->value)
                    ->live(),

                Select::make('quarter')->selectablePlaceholder(false)
                    ->label('Trimestre')
                    ->options([1 => 'T1', 2 => 'T2', 3 => 'T3', 4 => 'T4'])
                    ->visible(fn (Get $get): bool => $get('type') === PeriodType::QUARTER->value)
                    ->live(),

                Select::make('year')->selectablePlaceholder(false)
                    ->label('Année')
                    ->options(collect(range((int) now()->year - 5, (int) now()->year + 1))
                        ->mapWithKeys(fn (int $year): array => [$year => (string) $year])
                        ->all())
                    ->required()
                    ->live(),

                Select::make('report_section')->selectablePlaceholder(false)
                    ->label('Section')
                    ->options([
                        '' => 'Toutes les sections',
                        'categories' => 'Par catégorie',
                        'partners' => 'Partenaires',
                        'donations' => 'Détail des dons',
                    ])
                    ->live(),

                Select::make('category_group')->selectablePlaceholder(false)
                    ->label('Famille de catégorie')
                    ->options(array_merge(
                        ['' => 'Toutes les familles'],
                        collect(CategoryGroup::cases())->mapWithKeys(
                            fn (CategoryGroup $group): array => [$group->value => $group->label()],
                        )->all(),
                    ))
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
        // `getState()` valide le formulaire. Or cette méthode est appelée à
        // chaque rendu Livewire : une validation ici ferait échouer la page
        // dès qu'un champ requis est temporairement vide. On lit l'état brut.
        $state = $this->rawSelectionState();

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

            $report = app(PartnershipReportService::class)->buildReport($churchId, $type, $year, $index);
            $report = app(PartnershipReportService::class)->filterByCategoryGroup($report, (string) ($this->data['category_group'] ?? ''));

            return $this->filterReportForDisplay($report);
        } catch (Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }
    }

    /**
     * Ajoute la section sélectionnée pour l'affichage. L'export et l'envoi
     * utilisent le rapport complet.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function filterReportForDisplay(array $report): array
    {
        $report['report_section'] = (string) ($this->data['report_section'] ?? '');

        $group = (string) ($this->data['category_group'] ?? '');

        if ($group !== '') {
            $report['category_group'] = $group;
            $report['category_group_label'] = CategoryGroup::tryFrom($group)?->label() ?? $group;
        } else {
            $report['category_group'] = '';
            $report['category_group_label'] = '';
        }

        return $report;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetReportFilters')
                ->label('Réinitialiser les filtres')
                ->color('gray')
                ->visible(fn (): bool => filled($this->data['report_section'] ?? null) || filled($this->data['category_group'] ?? null))
                ->action(function (): void {
                    $this->data['report_section'] = '';
                    $this->data['category_group'] = '';
                    $this->form->fill($this->data);
                }),

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
                        $report = $reports->filterByCategoryGroup($report, (string) ($this->data['category_group'] ?? ''));
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
                                (string) ($this->data['category_group'] ?? ''),
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

    /** État brut du formulaire, sans validation (affichage uniquement). */
    private function rawSelectionState(): array
    {
        return is_array($this->data) ? $this->data : [];
    }

    /** @return array{0: User, 1: int, 2: PeriodType, 3: int, 4: int|null} */
    private function readSelection(): array
    {
        $state = $this->rawSelectionState();
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

    /**
     * Cases à cocher des copies : toutes cochées par défaut, l'utilisateur peut
     * en décocher pour exclure une personne de l'envoi.
     *
     * @return array<int, CheckboxList> | null
     */
    private function sendCcSchema(): ?array
    {
        try {
            [$user, $churchId, $type, $year, $index] = $this->readSelection();
            $cc = app(PartnershipReportSender::class)
                ->preview($user, $churchId, $type, $year, $index)['cc'] ?? [];

            if ($cc === []) {
                return null;
            }

            $options = [];

            foreach ($cc as $recipient) {
                $email = (string) $recipient['email'];
                $name = trim((string) ($recipient['name'] ?? ''));
                $options[$email] = $name === '' ? $email : $name.' <'.$email.'>';
            }

            return [
                CheckboxList::make('cc_emails')
                    ->label('Copie — destinataires à conserver')
                    ->options($options)
                    ->default(array_keys($options))
                    ->helperText('Décochez une personne pour ne pas l’envoyer en copie.'),
            ];
        } catch (Throwable) {
            return null;
        }
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
                $description .= ' · Copie : '.count($preview['cc']).' destinataire(s), à confirmer ci-dessous';
            }

            return $description;
        } catch (Throwable $exception) {
            return $exception->getMessage();
        }
    }
}
