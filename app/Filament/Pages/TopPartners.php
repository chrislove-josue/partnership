<?php

namespace App\Filament\Pages;

use App\Enums\CategoryGroup;
use App\Enums\PeriodType;
use App\Models\CategoryDon;
use App\Models\Church;
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
        // Même règle que les autres pages (dons, partenaires, cellules, rapport) :
        // l'église par défaut est celle du compte, sinon la première accessible.
        $actor = Auth::user();

        $this->form->fill([
            'period' => PeriodType::MONTH->value,
            'month' => (int) now()->month,
            'quarter' => (int) now()->quarter,
            'year' => (int) now()->year,
            'church_id' => (string) (app(ChurchAccessService::class)->defaultChurchId($actor) ?? ''),
            'category_group' => '',
            'category_id' => '',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $months = collect([
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
            7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ])->all();

        $actor = Auth::user();
        $churches = $actor
            ? app(ChurchAccessService::class)->accessibleChurches($actor)
            : collect();

        // L'église affectée au pasteur est proposée en premier.
        $assignedId = (int) (Auth::user()?->assigned_church_id ?? 0);
        $orderedChurches = $assignedId
            ? $churches->sortByDesc(fn (Church $church): bool => $church->id === $assignedId)
            : $churches;

        return $schema
            ->components([
                Select::make('church_id')->selectablePlaceholder(false)
                    ->label('Église')
                    ->options(
                        ['' => 'Toutes les églises accessibles', 'all' => 'Toutes les églises de la base']
                        + $orderedChurches->pluck('name', 'id')->all()
                    )
                    ->searchable()
                    ->live(),

                Select::make('category_group')->selectablePlaceholder(false)
                    ->label('Famille')
                    ->options(array_merge(
                        ['' => 'Toutes'],
                        collect(CategoryGroup::cases())->mapWithKeys(
                            fn (CategoryGroup $group): array => [$group->value => $group->label()],
                        )->all(),
                    ))
                    ->live(),

                Select::make('category_id')
                    ->label('Catégorie')
                    ->placeholder('Toutes les catégories')
                    ->options(fn (Get $get): array => CategoryDon::query()
                        ->when($get('category_group'), fn ($query, string $group) => $query->where('group', $group))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->live(),

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
            ->columns(3)
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
            $accessibleIds = array_map('intval', $actor ? app(ChurchAccessService::class)->accessibleChurchIds($actor) : []);
            $churchId = (string) ($state['church_id'] ?? '');
            // Une église précise, toutes les églises de la base, ou le périmètre.
            $churchIds = $churchId === 'all'
                ? Church::query()->pluck('id')->map('intval')->all()
                : ((int) $churchId && in_array((int) $churchId, $accessibleIds, true)
                    ? [(int) $churchId]
                    : $accessibleIds);

            $groupValue = (string) ($state['category_group'] ?? '');
            $group = CategoryGroup::tryFrom($groupValue);
            $categoryId = (int) ($state['category_id'] ?? 0);

            $items = $service->rank(
                $churchIds,
                $period,
                $year,
                $index,
                20,
                $group,
                $categoryId ?: null,
            );

            [$from, $to] = $service->range($period, $year, $index);

            // Le libellé reflète TOUJOURS les églises réellement utilisées pour
            // le calcul, jamais uniquement la sélection (évite tout décalage).
            $churchName = $churchId === 'all'
                ? 'Toutes les églises de la base'
                : (count($churchIds) === 1
                    ? Church::query()->whereKey($churchIds[0])->value('name')
                    : 'Toutes les églises accessibles');

            return [
                'items' => $items,
                'label' => Str::ucfirst($period->label()),
                'range' => $from->format('d/m/Y').' – '.$to->format('d/m/Y'),
                'church' => $churchName,
            ];
        } catch (\Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }
    }
}
