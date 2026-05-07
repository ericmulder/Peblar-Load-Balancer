<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('peblar:fetch-solar')]
#[Description('Fetch solar generation forecast from Open-Meteo (free, no API key)')]
class FetchSolarForecast extends Command
{
    public function handle(\App\Services\SolarForecastService $solar): int
    {
        if (!$solar->isEnabled()) {
            $this->line('Solar forecast staat uit — sla dit over.');
            return Command::SUCCESS;
        }

        if (!$solar->isConfigured()) {
            $this->warn('Solar forecast niet geconfigureerd. Stel lat/lon en kWp in via Instellingen.');
            return Command::SUCCESS;
        }

        $this->info('Solar forecast ophalen via Open-Meteo...');
        $ok = $solar->fetchAndStore();
        $this->info($ok ? 'Solar forecast bijgewerkt.' : 'Ophalen mislukt. Zie log voor details.');

        return $ok ? Command::SUCCESS : Command::FAILURE;
    }
}
