<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class P1MeterService
{
    private string $ip;

    public function __construct()
    {
        $this->ip = (string) Setting::get('p1_ip', config('peblar.p1_ip'));
    }

    /**
     * Read one SSE event burst from the /events endpoint using a raw socket.
     * ESPHome sends all sensor states immediately on connect, then keeps streaming.
     * We read for up to 5 seconds and take the first full burst.
     */
    public function getData(): ?array
    {
        if ($this->ip === '') {
            return null;
        }
        try {
            $sock = @stream_socket_client(
                "tcp://{$this->ip}:80",
                $errno,
                $errstr,
                5
            );

            if (!$sock) {
                Log::warning("P1 meter connection failed: {$errstr}");
                return null;
            }

            stream_set_timeout($sock, 5);

            $request = "GET /events HTTP/1.1\r\n"
                . "Host: {$this->ip}\r\n"
                . "Accept: text/event-stream\r\n"
                . "Connection: close\r\n\r\n";

            fwrite($sock, $request);

            $buffer = '';
            $deadline = microtime(true) + 5;

            while (!feof($sock) && microtime(true) < $deadline) {
                $chunk = fread($sock, 4096);
                if ($chunk === false || $chunk === '') break;
                $buffer .= $chunk;

                // Stop once we have all initial state events (ping + all sensors)
                // The slimmelezer sends a 'ping' event followed by all state events
                // We stop when we have power_consumed AND power_produced
                if (str_contains($buffer, 'sensor-power_consumed')
                    && str_contains($buffer, 'sensor-power_produced')
                    && str_contains($buffer, 'sensor-voltage_phase_3')) {
                    break;
                }
            }

            fclose($sock);

            if (empty($buffer)) {
                return null;
            }

            return $this->parseEvents($buffer);
        } catch (\Throwable $e) {
            Log::warning('P1 meter unreachable: ' . $e->getMessage());
            return null;
        }
    }

    private function parseEvents(string $body): array
    {
        $sensors = [];

        // Parse SSE format: "event: state\ndata: {...}\n"
        $lines = explode("\n", $body);
        $currentEvent = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'event:')) {
                $currentEvent = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:') && $currentEvent === 'state') {
                $json = trim(substr($line, 5));
                $data = json_decode($json, true);
                if ($data && isset($data['id'], $data['value'])) {
                    $id = $data['id'];
                    $sensors[$id] = $data['value'];
                }
                $currentEvent = null;
            }
        }

        if (empty($sensors)) {
            return ['online' => false];
        }

        return [
            'online' => true,
            'power_consumed_w' => isset($sensors['sensor-power_consumed'])
                ? (int) round($sensors['sensor-power_consumed'] * 1000)
                : null,
            'power_produced_w' => isset($sensors['sensor-power_produced'])
                ? (int) round($sensors['sensor-power_produced'] * 1000)
                : null,
            'voltage_l1' => $sensors['sensor-voltage_phase_1'] ?? null,
            'voltage_l2' => $sensors['sensor-voltage_phase_2'] ?? null,
            'voltage_l3' => $sensors['sensor-voltage_phase_3'] ?? null,
            'energy_consumed_t1_kwh' => $sensors['sensor-energy_consumed_tariff_1'] ?? null,
            'energy_consumed_t2_kwh' => $sensors['sensor-energy_consumed_tariff_2'] ?? null,
            'energy_produced_t1_kwh' => $sensors['sensor-energy_produced_tariff_1'] ?? null,
            'energy_produced_t2_kwh' => $sensors['sensor-energy_produced_tariff_2'] ?? null,
            'raw' => $sensors,
        ];
    }
}
