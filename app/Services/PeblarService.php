<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PeblarService
{
    private string $baseUrl;
    private string $token;
    private bool $configured;

    public function __construct()
    {
        $ip = (string) Setting::get('peblar_ip', config('peblar.ip'));
        $this->token = (string) Setting::get('peblar_token', config('peblar.token'));
        $this->configured = $ip !== '';
        $this->baseUrl = $this->configured ? "http://{$ip}/api/wlac/v1" : '';
    }

    private function headers(): array
    {
        return ['Authorization' => $this->token];
    }

    public function getMeter(): ?array
    {
        if (!$this->configured) {
            return null;
        }
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(5)
                ->get("{$this->baseUrl}/meter");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (ConnectionException $e) {
            Log::warning('Peblar meter unreachable: ' . $e->getMessage());
        }

        return null;
    }

    public function getEvInterface(): ?array
    {
        if (!$this->configured) {
            return null;
        }
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(5)
                ->get("{$this->baseUrl}/evinterface");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (ConnectionException $e) {
            Log::warning('Peblar evinterface unreachable: ' . $e->getMessage());
        }

        return null;
    }

    public function getSystem(): ?array
    {
        if (!$this->configured) {
            return null;
        }
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(5)
                ->get("{$this->baseUrl}/system");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (ConnectionException $e) {
            Log::warning('Peblar system unreachable: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Switch between 1-phase and 3-phase charging.
     * Uses the Force1Phase field on /evinterface (confirmed in Peblar REST API v1 docs).
     */
    public function setPhaseCount(int $phases): bool
    {
        if (!$this->configured) {
            return false;
        }
        $force1Phase = $phases === 1;

        try {
            $response = Http::withHeaders(array_merge($this->headers(), [
                'Content-Type' => 'application/json',
            ]))->timeout(5)
                ->patch("{$this->baseUrl}/evinterface", [
                    'Force1Phase' => $force1Phase,
                ]);

            if ($response->successful()) {
                Log::info('Peblar fase ingesteld: ' . ($force1Phase ? '1-fase' : '3-fase'));
                return true;
            }

            Log::error('Peblar fase-instelling mislukt: ' . $response->body());
        } catch (ConnectionException $e) {
            Log::error('Peblar fase-instelling verbindingsfout: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Set the charge current limit in mA.
     * 0 = stop charging, <6000 = stop charging, >=6000 = start/continue
     */
    public function setChargeCurrentLimit(int $milliamps): bool
    {
        if (!$this->configured) {
            return false;
        }
        try {
            $response = Http::withHeaders(array_merge($this->headers(), [
                'Content-Type' => 'application/json',
            ]))->timeout(5)
                ->patch("{$this->baseUrl}/evinterface", [
                    'ChargeCurrentLimit' => $milliamps,
                ]);

            if ($response->successful()) {
                Log::info("Peblar charge current set to {$milliamps}mA");
                return true;
            }

            Log::error('Peblar set current failed: ' . $response->body());
        } catch (ConnectionException $e) {
            Log::error('Peblar set current connection error: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Returns combined data from meter + evinterface in one call
     */
    public function getAllData(): array
    {
        $meter = $this->getMeter();
        $evInterface = $this->getEvInterface();

        return [
            'meter' => $meter,
            'evinterface' => $evInterface,
            'online' => $meter !== null,
        ];
    }
}
