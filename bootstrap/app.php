<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureCompany;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureTwoFactorForAdmins;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['company' => EnsureCompany::class, 'platform.admin' => EnsurePlatformAdmin::class, 'two_factor.admins' => EnsureTwoFactorForAdmins::class]);
        // Étape 8 : compte désactivé = déconnexion (toutes les requêtes web, Livewire compris).
        $middleware->web(append: [EnsureAccountActive::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route(auth()->user()?->homeRoute() ?? 'dashboard'));
        // --- Étape 9 ---
        $middleware->append(SecurityHeaders::class);
        $middleware->authenticateSessions(); // invalide les sessions des autres appareils après changement de mot de passe
        // --- fin étape 9 ---
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ? explode(',', env('TRUSTED_PROXIES')) : null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
