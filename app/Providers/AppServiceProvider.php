<?php

namespace App\Providers;

use App\Auth\LogoutResponse;
use App\Listeners\RecordLoginHistory;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LogoutResponseContract::class, LogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Certaines clés Filament ne sont pas encore traduites en français dans le paquet.
        // On charge d'abord les traductions du paquet pour ne pas écraser les clés existantes.
        Lang::load('filament-panels', 'layout', 'fr');
        Lang::load('filament-tables', 'table', 'fr');
        Lang::load('filament-forms', 'components', 'fr');

        Lang::addLines([
            'layout.skip_to_content.label' => 'Aller au contenu',
            'layout.navigation.label' => 'Navigation latérale',
            'layout.topbar.label' => 'Barre supérieure',
            'layout.actions.theme_switcher.label' => 'Thème',
        ], 'fr', 'filament-panels');

        Lang::addLines([
            'table.loading' => 'Chargement…',
            'table.result_count' => '{0} Aucun résultat|{1} :count résultat|[2,*] :count résultats',
            'table.columns.icon.boolean.true' => 'Oui',
            'table.columns.icon.boolean.false' => 'Non',
        ], 'fr', 'filament-tables');

        Lang::addLines([
            'components.color_picker.panel_label' => 'Sélecteur de couleur',
            'components.key_value.columns.actions.label' => 'Actions',
            'components.select.actions.clear.label' => 'Effacer la sélection',
            'components.select.actions.remove_option.label' => 'Retirer :label',
            'components.select.search_label' => 'Rechercher',
        ], 'fr', 'filament-forms');

        Event::listen([Login::class, Logout::class], RecordLoginHistory::class);
    }
}
