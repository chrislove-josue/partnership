<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Ce cookie est écrit par le navigateur : Laravel ne doit pas tenter de le déchiffrer.
        // `env()` ici car le dépôt de configuration n'est pas encore disponible à ce stade.
        $middleware->encryptCookies(except: [env('SESSION_CONSENT_COOKIE', 'churchmanager_cookie_consent')]);

        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
