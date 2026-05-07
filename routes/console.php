<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Load balancer runs every minute (artisan schedule:run must be in crontab)
Schedule::command('peblar:balance')->everyMinute()->withoutOverlapping();

// Fetch Zonneplan prices every hour
Schedule::command('peblar:fetch-prices')->hourly()->withoutOverlapping();

// Fetch solar generation forecast dagelijks om 06:00 (gratis Open-Meteo, geen API key)
Schedule::command('peblar:fetch-solar')->dailyAt('06:00')->withoutOverlapping();

// Fetch vehicle status:
// - Alleen automatisch elke 5 minuten als de auto ingeplugd is (vehicle_plugged_in = 1)
// - Als de auto NIET ingeplugd is: geen automatische polls (12V accu sparen)
//   In dat geval kan de gebruiker handmatig refreshen via het dashboard.
Schedule::command('peblar:fetch-vehicle')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->when(function () {
        // Controleer of de auto ingeplugd is via de laatste bekende voertuigstatus
        return (bool) \App\Models\MeterReading::latest('recorded_at')
            ->whereNotNull('vehicle_plugged_in')
            ->value('vehicle_plugged_in');
    });
