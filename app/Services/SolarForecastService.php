<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SolarForecast;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SolarForecastService
{
    // Open-Meteo gratis endpoint — geen API key nodig
    const API_URL = 'https://api.open-meteo.com/v1/forecast';

    // Systeemrendement (omvormer + bekabeling + temperatuurverlies)
    const SYSTEM_EFFICIENCY = 0.80;

    public function isEnabled(): bool
    {
        return (bool) Setting::get('solar_forecast_enabled', false);
    }

    public function isConfigured(): bool
    {
        $lat = (float) Setting::get('solar_latitude', 0);
        $lon = (float) Setting::get('solar_longitude', 0);
        $kwp = (float) Setting::get('solar_panel_power_kwp', 0);

        return $lat !== 0.0 && $lon !== 0.0 && $kwp > 0;
    }

    /**
     * Haal solar forecast op van Open-Meteo en sla op in solar_forecasts.
     *
     * Open-Meteo geeft `global_tilted_irradiance` terug in W/m² per uur.
     * Formule: wh_expected = gti_wm2 * panel_power_kwp * SYSTEM_EFFICIENCY
     *
     * Open-Meteo azimuth-conventie: 0° = Zuid, -90° = Oost, 90° = West, ±180° = Noord (-180..180).
     * Instellingen worden opgeslagen in Noord-gebaseerde conventie (0° = Noord, 90° = Oost, 180° = Zuid, 270° = West).
     * Conversie: api_azimuth = setting_azimuth - 180
     */
    public function fetchAndStore(): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('SolarForecastService: niet geconfigureerd (lat/lon of kWp ontbreekt)');
            return false;
        }

        $lat     = (float) Setting::get('solar_latitude');
        $lon     = (float) Setting::get('solar_longitude');
        $kwp     = (float) Setting::get('solar_panel_power_kwp');
        $tilt    = (int)   Setting::get('solar_panel_tilt', 30);
        $azimuth = (int)   Setting::get('solar_panel_azimuth', 315);

        // Open-Meteo verwacht azimuth in -180..180 (Zuid-gebaseerd).
        // Gebruikersinvoer is Noord-gebaseerd (0-360): trek 180 af voor conversie.
        $azimuthApi = $azimuth - 180;

        try {
            $response = Http::timeout(10)->get(self::API_URL, [
                'latitude'             => $lat,
                'longitude'            => $lon,
                'hourly'               => 'global_tilted_irradiance',
                'tilt'                 => $tilt,
                'azimuth'              => $azimuthApi,
                'timezone'             => 'Europe/Amsterdam',
                'forecast_days'        => 3,
                'models'               => 'best_match',
            ]);

            if (!$response->successful()) {
                Log::warning('SolarForecastService: Open-Meteo HTTP ' . $response->status());
                return false;
            }

            $data = $response->json();
            $times = $data['hourly']['time'] ?? [];
            $gtis  = $data['hourly']['global_tilted_irradiance'] ?? [];

            if (empty($times) || count($times) !== count($gtis)) {
                Log::warning('SolarForecastService: onverwacht Open-Meteo antwoord');
                return false;
            }

            $rows = [];
            foreach ($times as $i => $timeStr) {
                $gti = $gtis[$i] ?? 0;
                $gti = max(0, (float) $gti); // negatieve waarden zijn onzin

                // Wh per uur = GTI (W/m²) / 1000 (kW/m²) * paneelvermogen (kW) * 1000 (Wh) * rendement
                // Vereenvoudigd: gti * kwp * efficiency
                $whExpected = round($gti * $kwp * self::SYSTEM_EFFICIENCY, 1);

                $hour = Carbon::parse($timeStr, 'Europe/Amsterdam')->utc();

                $rows[] = [
                    'hour'       => $hour->toDateTimeString(),
                    'wh_expected'=> $whExpected,
                    'gti_wm2'    => round($gti, 1),
                    'fetched_at' => now()->toDateTimeString(),
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ];
            }

            if (empty($rows)) {
                Log::warning('SolarForecastService: geen uren verwerkt');
                return false;
            }

            DB::table('solar_forecasts')->upsert(
                $rows,
                ['hour'],
                ['wh_expected', 'gti_wm2', 'fetched_at', 'updated_at']
            );

            Log::info(sprintf(
                'SolarForecastService: %d uren opgeslagen (lat=%.4f lon=%.4f tilt=%d° az=%d°→%d°)',
                count($rows), $lat, $lon, $tilt, $azimuth, $azimuthApi
            ));

            return true;
        } catch (\Throwable $e) {
            Log::warning('SolarForecastService: ophalen mislukt — ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Geeft de komende 48u solar forecast terug voor gebruik in UI/charts.
     */
    public function getForecast(): array
    {
        return SolarForecast::where('hour', '>=', now()->startOfHour())
            ->where('hour', '<=', now()->addDays(2))
            ->orderBy('hour')
            ->get(['hour', 'wh_expected', 'gti_wm2'])
            ->map(fn($f) => [
                'hour'        => $f->hour->toIso8601ZuluString(),
                'wh_expected' => $f->wh_expected,
                'gti_wm2'     => $f->gti_wm2,
            ])
            ->toArray();
    }
}
