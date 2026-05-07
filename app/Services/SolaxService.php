<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SolaxService
{
    private string $ip;
    private string $password;

    // Data array indices for type=16 (confirmed via live device readings)
    const IDX_PV_POWER_1      = 15;  // PV string 1 power (W)
    const IDX_PV_POWER_2      = 16;  // PV string 2 power (W)
    const IDX_BATTERY_POWER   = 41;  // Battery charge/discharge power (W, negative=discharge)
    const IDX_BATTERY_SOC     = 26;  // Battery state of charge (%)
    const IDX_GRID_POWER      = 32;  // Grid feed-in/draw power (W, negative=feed-in)

    public function __construct()
    {
        $this->ip = (string) Setting::get('solax_ip', config('peblar.solax_ip'));
        $this->password = (string) Setting::get('solax_password', config('peblar.solax_password'));
    }

    public function getData(): ?array
    {
        if ($this->ip === '') {
            return null;
        }
        try {
            $response = Http::timeout(8)
                ->withHeaders(['X-Forwarded-For' => '5.8.8.8'])
                ->asForm()
                ->post("http://{$this->ip}/", ['optType' => 'ReadRealTimeData', 'pwd' => $this->password]);

            if (!$response->successful()) {
                Log::warning('Solax API returned: ' . $response->status());
                return null;
            }

            $json = $response->json();

            if (!isset($json['Data']) || !is_array($json['Data'])) {
                Log::warning('Solax response missing Data array');
                return null;
            }

            return $this->parseData($json);
        } catch (ConnectionException $e) {
            Log::warning('Solax unreachable: ' . $e->getMessage());
            return null;
        }
    }

    private function parseData(array $json): array
    {
        $data = $json['Data'];
        $type = $json['type'] ?? 0;

        $pv1 = $this->getIndex($data, self::IDX_PV_POWER_1);
        $pv2 = $this->getIndex($data, self::IDX_PV_POWER_2);
        $pvPower = ($pv1 ?? 0) + ($pv2 ?? 0);

        $batteryPower = $this->getIndex($data, self::IDX_BATTERY_POWER);
        $batterySoc   = $this->getIndex($data, self::IDX_BATTERY_SOC);
        $gridPower    = $this->getIndex($data, self::IDX_GRID_POWER);

        return [
            'online'          => true,
            'type'            => $type,
            'pv_power_w'      => $pvPower,
            'pv1_power_w'     => $pv1,
            'pv2_power_w'     => $pv2,
            'battery_power_w' => $batteryPower, // positive = charging, negative = discharging
            'battery_soc'     => $batterySoc,
            'grid_power_w'    => $gridPower,    // positive = import, negative = export
            'raw_count'       => count($data),
        ];
    }

    private function getIndex(array $data, int $index): ?int
    {
        return isset($data[$index]) ? (int) $data[$index] : null;
    }
}
