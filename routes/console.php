<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Campagnes programmées : vérification chaque minute (le planificateur doit tourner : php artisan schedule:work ou cron).
Schedule::command('campaigns:dispatch-due')->everyMinute()->withoutOverlapping();
