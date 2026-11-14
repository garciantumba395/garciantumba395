<?php

namespace App\Providers;

use App\Domain\Sms\SmsManager;
use App\Domain\Sms\SmsProvider;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentCompany::class);
        $this->app->singleton(SmsManager::class);
        // Toute résolution directe de SmsProvider passe par le verrou d'activation du manager.
        $this->app->bind(SmsProvider::class, fn ($app) => $app->make(SmsManager::class)->provider());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
