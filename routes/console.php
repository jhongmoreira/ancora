<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ver docs/10 — requer o cron do sistema chamando `schedule:run` a cada minuto.
Schedule::command('reminders:dispatch')->everyMinute();
