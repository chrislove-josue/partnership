<?php

use App\Filament\Pages\Auth\Login;
use App\Http\Controllers\MagicLoginController;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// "/" envoie directement vers la connexion (comportement demandé, identique à Next.js).
Route::get('/', fn () => redirect('/login'));

// Page de connexion servie directement sur /login, avec le contexte du panneau Filament.
Route::get('/login', Login::class)
    ->middleware([
        'panel:admin',
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
    ->name('login');

Route::get('/magic', MagicLoginController::class)->name('magic.login');
