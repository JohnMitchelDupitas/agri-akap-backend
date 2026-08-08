<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Refresh Echague Open-Meteo cache twice daily (Asia/Manila mornings & evenings).
Schedule::command('weather:fetch')
    ->twiceDaily(5, 17)
    ->timezone('Asia/Manila')
    ->withoutOverlapping();

// After the morning fetch settles, evaluate tomorrow's risk and SMS farmers if needed.
Schedule::command('weather:alert')
    ->dailyAt('06:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();
