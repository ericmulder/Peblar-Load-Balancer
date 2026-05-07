<?php

namespace App\Services;

use App\Models\PriceForecast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZonneplanService
{
    // Public page — no login required
    const PUBLIC_URL = 'https://www.zonneplan.nl/energie/dynamische-energieprijzen/stroomprijs';

    // priceTotalTaxIncluded is in units of 0.1 micro-euro: divide by 10_000_000 to get EUR/kWh
    // e.g. 2281895 / 10_000_000 = 0.2282 EUR/kWh = ~22.8ct/kWh
    const PRICE_DIVISOR = 10_000_000;

    public function isConfigured(): bool
    {
        return true; // Always available via public page
    }

    /**
     * Fetch and store hourly price forecasts from Zonneplan's public page.
     * No account required — scrapes the embedded GraphQL response.
     */
    public function fetchAndStorePrices(): bool
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['Accept' => 'text/html'])
                ->get(self::PUBLIC_URL);

            if (!$response->successful()) {
                Log::warning('Zonneplan public page unavailable: ' . $response->status());
                return false;
            }

            $html = $response->body();

            // The page embeds price data as backslash-escaped JSON inside a script tag.
            // Search for the escaped \"hours\":[ pattern, then unescape the array.
            $needle = '\\"hours\\":[';
            $pos = strpos($html, $needle);
            if ($pos === false) {
                Log::warning('Zonneplan: geen uurprijzen gevonden in paginabron');
                return false;
            }

            // Extract from the '[' to the matching ']' (counting escaped brackets)
            $start = $pos + strlen($needle) - 1; // points to '['
            $depth = 0;
            $end   = $start;
            $len   = strlen($html);
            while ($end < $len) {
                $c = $html[$end];
                if ($c === '[' || $c === '{') $depth++;
                elseif ($c === ']' || $c === '}') { $depth--; if ($depth === 0) { $end++; break; } }
                $end++;
            }

            $raw   = substr($html, $start, $end - $start);
            $hours = json_decode(stripcslashes($raw), true);

            if (!is_array($hours)) {
                Log::warning('Zonneplan: JSON parse mislukt');
                return false;
            }

            $rows = [];
            foreach ($hours as $hour) {
                $dt    = $hour['dateTime'] ?? null;
                $price = $hour['priceTotalTaxIncluded'] ?? null;

                if (!$dt || $price === null) continue;

                $rows[] = [
                    'hour'               => \Carbon\Carbon::parse($dt, 'Europe/Amsterdam')->utc()->format('Y-m-d H:i:s'),
                    'price_eur_incl_tax' => round($price / self::PRICE_DIVISOR, 5),
                    'price_eur_excl_tax' => isset($hour['priceInclHandlingVat'])
                        ? round($hour['priceInclHandlingVat'] / self::PRICE_DIVISOR, 5)
                        : null,
                    'source'     => 'zonneplan_public',
                    'fetched_at' => now()->toDateTimeString(),
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ];
            }

            if (empty($rows)) {
                Log::warning('Zonneplan: geen verwerkte uren');
                return false;
            }

            // Upsert: update existing rows, insert new ones — race-condition safe
            DB::table('price_forecasts')->upsert(
                $rows,
                ['hour'],
                ['price_eur_incl_tax', 'price_eur_excl_tax', 'source', 'fetched_at', 'updated_at']
            );

            Log::info('Zonneplan publieke prijzen bijgewerkt: ' . count($rows) . ' uur');
            return true;
        } catch (\Throwable $e) {
            Log::warning('Zonneplan prijzen ophalen mislukt: ' . $e->getMessage());
            return false;
        }
    }

    public function getCurrentPrice(): ?float
    {
        return PriceForecast::currentPrice();
    }

    /**
     * Get next 24h forecast for dashboard chart
     */
    public function getForecast(): array
    {
        return PriceForecast::where('hour', '>=', now()->startOfHour())
            ->where('hour', '<=', now()->addDay())
            ->orderBy('hour')
            ->get(['hour', 'price_eur_incl_tax'])
            ->map(fn ($f) => [
                'hour'               => $f->hour->toIso8601ZuluString(), // "2026-04-09T17:00:00Z" so JS treats as UTC
                'price_eur_incl_tax' => $f->price_eur_incl_tax,
            ])
            ->toArray();
    }
}
