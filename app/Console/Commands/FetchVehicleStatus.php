<?php

namespace App\Console\Commands;

use App\Models\MeterReading;
use App\Models\Setting;
use App\Services\HyundaiService;
use Illuminate\Console\Command;

class FetchVehicleStatus extends Command
{
    protected $signature   = 'peblar:fetch-vehicle {--force : Forceer ophalen, negeer cache}';
    protected $description = 'Haal actuele voertuigdata op van Hyundai BlueLink en sla op in DB';

    public function handle(HyundaiService $hyundai): int
    {
        if (!$hyundai->isConfigured()) {
            $this->line('Hyundai BlueLink niet geconfigureerd — sla over.');
            return self::SUCCESS;
        }

        $this->line('[' . now()->format('H:i:s') . '] Voertuigdata ophalen...');

        // Detecteer einde laadsessie: Peblar laadt niet meer maar laatste vehicle-reading zei wel laden.
        // In dat geval vragen we de auto actief om zijn huidige staat (--live) voor actuele SoC.
        $sessionEnded = !$this->option('force') && $this->sessionJustEnded();
        $live = $this->option('force') || $sessionEnded;

        if ($sessionEnded) {
            $this->line('  ⚡ Laadsessie zojuist beëindigd — live status opvragen bij auto');
        }

        $data = $hyundai->getData(forceRefresh: (bool) $this->option('force'), live: $live);

        if (!$data) {
            $this->warn('Geen data ontvangen (auto offline of fout).');
            return self::FAILURE;
        }

        if (!empty($data['_cached'])) {
            $this->line('  ↩ Gecachte data gebruikt (API niet bereikbaar)');
        }

        // Sla op bij de meest recente MeterReading
        $latest = MeterReading::latest('recorded_at')->first();
        if ($latest) {
            $latest->update([
                'vehicle_soc'              => $data['soc'],
                'vehicle_charging'         => $data['is_charging'],
                'vehicle_plugged_in'       => $data['is_plugged_in'],
                'vehicle_range_km'         => $data['range_km'],
                'vehicle_minutes_to_full'  => $data['minutes_to_full'],
                'vehicle_last_updated_at'  => !empty($data['last_updated']) ? $data['last_updated'] : null,
            ]);
        }

        // Beheer polling-suspensie:
        // - Auto is NIET aan het laden na deze fetch → pauzeer automatische polls.
        //   Dit geldt zowel na een sessie-einde als bij een initiële plug-in zonder laden.
        // - Clearing van de suspensie gebeurt in de scheduler zodra CP state = 'C' is.
        if (!$data['is_charging']) {
            $this->suspendPolling();
            if ($live && $sessionEnded) {
                $this->line('  ⏸ Polling gepauzeerd — hervatten bij volgende laadsessie');
            }
        }

        $this->info(sprintf(
            '  SoC=%s%% | %s | Bereik=%skm%s',
            $data['soc'] ?? '?',
            $data['is_charging']   ? 'Aan het laden' :
                ($data['is_plugged_in'] ? 'Ingeplugd, niet aan het laden' : 'Niet aangesloten'),
            $data['range_km'] ?? '?',
            $data['minutes_to_full'] ? ' | ' . $data['minutes_to_full'] . ' min tot vol' : ''
        ));

        return self::SUCCESS;
    }

    /**
     * Bepaal of een laadsessie zojuist is beëindigd.
     *
     * Combinatie van twee signalen:
     *  1. De laatste vehicle-reading gaf aan dat de auto aan het laden was
     *  2. De Peblar laadpaal is nu NIET in state C (geen actieve laadstroom)
     */
    private function sessionJustEnded(): bool
    {
        $lastVehicle = MeterReading::whereNotNull('vehicle_charging')
            ->latest('recorded_at')
            ->first();

        if (!$lastVehicle || !$lastVehicle->vehicle_charging) {
            return false;
        }

        $lastPeblar = MeterReading::whereNotNull('peblar_cp_state')
            ->latest('recorded_at')
            ->first();

        if (!$lastPeblar) {
            return false;
        }

        return $lastPeblar->peblar_cp_state !== 'C';
    }

    /**
     * Pauzeer automatische polling via de settings-tabel.
     * Wordt hervat door de scheduler zodra de Peblar opnieuw in CP state C gaat.
     */
    private function suspendPolling(): void
    {
        Setting::where('key', 'hyundai_polling_suspended')->update(['value' => '1']);
    }
}
