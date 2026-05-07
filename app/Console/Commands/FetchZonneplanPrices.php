<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('peblar:fetch-prices')]
#[Description('Fetch dynamic electricity prices from Zonneplan')]
class FetchZonneplanPrices extends Command
{
    public function handle(\App\Services\ZonneplanService $zonneplan): int
    {
        if (!$zonneplan->isConfigured()) {
            $this->warn('Zonneplan niet geconfigureerd. Voer e-mail en wachtwoord in via Instellingen.');
            return Command::SUCCESS;
        }

        $this->info('Zonneplan prijzen ophalen...');
        $ok = $zonneplan->fetchAndStorePrices();
        $this->info($ok ? 'Prijzen bijgewerkt.' : 'Ophalen mislukt. Zie log voor details.');

        return $ok ? Command::SUCCESS : Command::FAILURE;
    }
}
