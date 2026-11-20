<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ApiSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-server';

    protected static ?string $navigationLabel = 'Adresse API';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Adresse de l’API mobile';

    protected string $view = 'filament.pages.api-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::check() && Auth::user()?->isSuperAdmin() === true;
    }

    public function mount(): void
    {
        $this->form->fill([
            'api_url' => Setting::get('api_url', 'http://172.16.206.66:8000/api'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('api_url')
                    ->label('Adresse de l’API')
                    ->helperText('URL utilisée par l’application FinanceMobile, terminée par /api.')
                    ->required()
                    ->url()
                    ->maxLength(255),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        Setting::set('api_url', rtrim((string) $state['api_url'], '/'));

        Notification::make()
            ->success()
            ->title('Adresse de l’API enregistrée.')
            ->send();
    }
}
