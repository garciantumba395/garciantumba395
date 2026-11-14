<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Campagnes programmées : vérification chaque minute (le planificateur doit tourner : php artisan schedule:work ou cron).
Schedule::command('campaigns:dispatch-due')->everyMinute()->withoutOverlapping();

// --- Étape 9 : surveillance quotidienne (le planificateur doit tourner : schedule:work ou cron, voir docs/OPERATIONS.md) ---
// Réévaluation des entreprises validées sur leurs envois réels récents (suspension automatique si dérive).
Schedule::command('sending:review')->dailyAt('03:00')->withoutOverlapping();
// Cohérence solde / grand livre ; un échec (code de sortie ≠ 0 ou exception) est journalisé en erreur, sans donnée.
Schedule::command('credits:verify')->dailyAt('03:30')->withoutOverlapping()
    ->onFailure(fn () => Log::error('credits:verify : la vérification quotidienne a échoué (incohérence ou erreur).'));
// --- fin étape 9 ---
