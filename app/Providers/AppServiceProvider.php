<?php

namespace App\Providers;

use App\Domain\Sms\InfobipSmsProvider;
use App\Domain\Sms\SmsManager;
use App\Domain\Sms\SmsProvider;
use App\Domain\Sms\Webhooks\SmsWebhookRegistry;
use App\Http\Middleware\EnsureEmailIsVerifiedIfRequired;
use App\Http\Middleware\EnsureTwoFactorForAdmins;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentCompany::class);
        $this->app->singleton(SmsManager::class, function () {
            $manager = new SmsManager;
            $manager->extend('infobip', fn () => new InfobipSmsProvider(
                (string) config('sms.infobip.base_url'), (string) config('sms.infobip.api_key'),
                config('sms.infobip.default_sender'), (int) config('sms.infobip.timeout')));

            return $manager;
        });
        $this->app->singleton(SmsWebhookRegistry::class); // étape 10 préparatoire : vérificateurs de webhook par fournisseur
        // Toute résolution directe de SmsProvider passe par le verrou d'activation du manager.
        $this->app->bind(SmsProvider::class, fn ($app) => $app->make(SmsManager::class)->provider());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Étape 10 préparatoire : limitation de débit des webhooks (par IP et fournisseur).
        RateLimiter::for('unsubscribe', fn (Request $request) => Limit::perMinute((int) config('legal.unsubscribe.rate_limit_per_minute'))->by('unsub|'.$request->ip()));
        RateLimiter::for('sms-webhooks', fn (Request $request) => Limit::perMinute((int) config('sms.webhooks.rate_limit_per_minute'))
            ->by($request->ip().'|'.$request->route('provider')));
        // --- Étape 9 : limitation de débit globale des requêtes Livewire (valeurs : config/security.php) ---
        RateLimiter::for('livewire', function (Request $request) {
            $c = config('security.rate_limits.livewire');

            return new Limit('livewire:'.($request->user()?->id ?? $request->ip()), (int) $c['max'], (int) $c['decay']);
        });
        Livewire::setUpdateRoute(fn ($handle) => Route::post('/livewire/update', $handle)->middleware('throttle:livewire'));
        Livewire::addPersistentMiddleware([EnsureEmailIsVerifiedIfRequired::class, EnsureTwoFactorForAdmins::class]);
        // --- fin étape 9 ---
    }
}
