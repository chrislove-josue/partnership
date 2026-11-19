<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Http\Middleware\Authenticate;
use Filament\Enums\UserMenuPosition;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('dashboard')
            ->theme(asset('css/filament/admin/theme.css'))
            ->brandName('ChurchManager')
            ->font('Geist', 'https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&display=swap')
            ->monoFont('Geist Mono', 'https://fonts.googleapis.com/css2?family=Geist+Mono:wght@400;500&display=swap')
            ->login(Login::class)
            ->profile()
            ->userMenu(position: UserMenuPosition::Sidebar)
            ->darkMode(false)
            ->sidebarWidth('18rem')
            ->maxContentWidth('80rem')
            ->simplePageMaxContentWidth('28rem')
            ->colors([
                'primary' => '#9333EA',
                'danger' => '#E7000B',
                'info' => '#3B82F6',
                'success' => '#10B981',
                'warning' => '#F59E0B',
                'gray' => '#6B7280',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
