<?php

namespace App\Services;

use App\Models\MeterReading;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class HyundaiService
{
    // Cache-sleutel voor laatste bekende voertuigdata
    const CACHE_KEY     = 'hyundai_vehicle_data';
    const CACHE_TTL_MIN = 5;    // minuten geldig
    const CACHE_TTL_MAX = 60;   // na fout: bewaar vorige data maximaal 1 uur

    private string  $username;
    private string  $refreshToken;
    private string  $pin;
    private bool    $enabled;

    public function __construct()
    {
        $this->username     = (string) Setting::get('hyundai_username', '');
        $this->refreshToken = (string) Setting::get('hyundai_refresh_token', '');
        $this->pin          = (string) Setting::get('hyundai_pin', '');
        $this->enabled      = (bool)   Setting::get('hyundai_enabled', false);
    }

    public function isConfigured(): bool
    {
        return $this->enabled
            && !empty($this->username)
            && !empty($this->refreshToken);
    }

    /**
     * Haal voertuigdata op (of geeft gecachte data terug als binnen TTL).
     *
     * @param  bool  $forceRefresh   Negeer PHP-cache, roep Python opnieuw aan
     * @param  bool  $live   Vraag actuele status op bij de auto zelf (gebruik na laadsessie)
     */
    public function getData(bool $forceRefresh = false, bool $live = false): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        // Live-state vraag nooit serveren vanuit cache
        if (!$forceRefresh && !$live && Cache::has(self::CACHE_KEY)) {
            return Cache::get(self::CACHE_KEY);
        }

        return $this->fetchFromPython($live);
    }

    /**
     * Roep Python-script aan en retourneer geparsed resultaat.
     *
     * @param  bool  $live  Geef --live door aan het script (forceert directe auto-query)
     */
    private function fetchFromPython(bool $live = false): ?array
    {
        $scriptPath = base_path('scripts/ioniq5_status.py');

        if (!file_exists($scriptPath)) {
            Log::warning('HyundaiService: Python script niet gevonden: ' . $scriptPath);
            return $this->cachedFallback();
        }

        $python = $this->findPython();
        if (!$python) {
            Log::warning('HyundaiService: Python3 niet gevonden op dit systeem');
            return $this->cachedFallback();
        }

        $args = [$python, $scriptPath, $this->username, $this->refreshToken, $this->pin];
        if ($live) {
            $args[] = '--live';
        }

        $env = [
            'HYUNDAI_CLIENT_ID'     => (string) config('peblar.hyundai_client_id', env('HYUNDAI_CLIENT_ID', '')),
            'HYUNDAI_CLIENT_SECRET' => (string) config('peblar.hyundai_client_secret', env('HYUNDAI_CLIENT_SECRET', '')),
        ];

        $process = new Process($args, env: $env, timeout: $live ? 60 : 30);

        try {
            $process->run();
        } catch (\Exception $e) {
            Log::warning('HyundaiService: Process fout: ' . $e->getMessage());
            return $this->cachedFallback();
        }

        $output = trim($process->getOutput());

        if (empty($output)) {
            Log::warning('HyundaiService: Geen output van Python script. Stderr: ' . $process->getErrorOutput());
            return $this->cachedFallback();
        }

        $data = json_decode($output, true);

        if (!is_array($data)) {
            Log::warning('HyundaiService: Ongeldige JSON output: ' . $output);
            return $this->cachedFallback();
        }

        if (isset($data['error'])) {
            Log::warning('HyundaiService: API fout: ' . $data['error']);
            return $this->cachedFallback();
        }

        // Sla op in cache voor 5 minuten
        Cache::put(self::CACHE_KEY, $data, now()->addMinutes(self::CACHE_TTL_MIN));

        Log::info(sprintf(
            'HyundaiService: SoC=%s%% charging=%s range=%skm [%s]',
            $data['soc'] ?? '?',
            $data['is_charging'] ? 'ja' : 'nee',
            $data['range_km'] ?? '?',
            $live ? 'live' : 'cached'
        ));

        return $data;
    }

    /**
     * Bewaar vorige bekende waarde als fallback (max 1 uur oud).
     */
    private function cachedFallback(): ?array
    {
        // Probeer vorige waarde uit DB
        $latest = MeterReading::whereNotNull('vehicle_soc')
            ->where('recorded_at', '>=', now()->subHour())
            ->latest('recorded_at')
            ->first();

        if (!$latest) {
            return null;
        }

        return [
            'soc'               => $latest->vehicle_soc,
            'is_charging'       => (bool) $latest->vehicle_charging,
            'is_plugged_in'     => (bool) $latest->vehicle_plugged_in,
            'range_km'          => $latest->vehicle_range_km,
            'minutes_to_full'   => $latest->vehicle_minutes_to_full,
            'charging_current_a' => null,
            'last_updated'      => $latest->vehicle_last_updated_at?->toIso8601String(),
            '_cached'           => true,
        ];
    }

    /**
     * Zoek de python3 executable — geeft de venv-versie terug als die bestaat.
     */
    private function findPython(): ?string
    {
        // Venv in scripts/.venv heeft prioriteit
        $venv = base_path('scripts/.venv/bin/python3');
        if (file_exists($venv)) {
            return $venv;
        }

        foreach (['python3', 'python', '/usr/local/bin/python3', '/usr/bin/python3'] as $cmd) {
            $check = new Process([$cmd, '--version']);
            try {
                $check->run();
                if ($check->isSuccessful()) {
                    return $cmd;
                }
            } catch (\Exception) {
                // probeer volgende
            }
        }
        return null;
    }
}
