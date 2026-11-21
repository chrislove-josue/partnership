<?php

namespace App\Filament\Pages;

use App\Models\LoginHistory as LoginHistoryModel;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class LoginHistory extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Historique de connexion';

    protected static string|\UnitEnum|null $navigationGroup = 'Mon compte';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Historique de connexion';

    protected string $view = 'filament.pages.login-history';

    /** @var array<string, mixed> */
    public array $filters = [];

    public function mount(): void
    {
        $this->form->fill([
            'search' => '',
            'event' => '',
            'source' => '',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('search')
                    ->label('Rechercher')
                    ->placeholder('Événement, source, IP ou appareil')
                    ->live(debounce: 300),
                Select::make('event')
                    ->label('Événement')
                    ->options([
                        LoginHistoryModel::EVENT_LOGIN => 'Connexion',
                        LoginHistoryModel::EVENT_LOGOUT => 'Déconnexion',
                    ])
                    ->placeholder('Tous les événements')
                    ->live(),
                Select::make('source')
                    ->label('Source')
                    ->options([
                        LoginHistoryModel::SOURCE_WEB => 'Site web',
                        LoginHistoryModel::SOURCE_MOBILE => 'Application mobile',
                    ])
                    ->placeholder('Toutes les sources')
                    ->live(),
            ])
            ->columns(3)
            ->statePath('filters');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetFilters')
                ->label('Réinitialiser les filtres')
                ->color('gray')
                ->visible(fn (): bool => filled($this->filters['search'] ?? null)
                    || filled($this->filters['event'] ?? null)
                    || filled($this->filters['source'] ?? null))
                ->action(function (): void {
                    $this->reset('filters');
                    $this->form->fill([
                        'search' => '',
                        'event' => '',
                        'source' => '',
                    ]);
                }),
        ];
    }

    /** @return LengthAwarePaginator<int, LoginHistoryModel> */
    public function getEntries(): LengthAwarePaginator
    {
        $query = LoginHistoryModel::query()
            ->where('user_id', Auth::id());

        $event = (string) ($this->filters['event'] ?? '');
        $source = (string) ($this->filters['source'] ?? '');

        if ($event !== '') {
            $query->where('event', $event);
        }

        if ($source !== '') {
            $query->where('source', $source);
        }

        $search = mb_strtolower(trim((string) ($this->filters['search'] ?? '')));

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $terms = [
                    LoginHistoryModel::EVENT_LOGIN => ['connexion', 'login'],
                    LoginHistoryModel::EVENT_LOGOUT => ['déconnexion', 'deconnexion', 'logout'],
                ];

                foreach ($terms as $value => $labels) {
                    if (collect($labels)->contains(fn (string $label): bool => str_contains($label, $search))) {
                        $query->orWhere('event', $value);
                    }
                }

                $query
                    ->orWhere('event', 'like', "%{$search}%")
                    ->orWhere('source', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('user_agent', 'like', "%{$search}%");
            });
        }

        return $query
            ->latest()
            ->paginate(20);
    }
}
