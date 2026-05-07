<?php

namespace App\Http\Controllers;

use App\Models\ChargeDecision;
use App\Models\MeterReading;
use App\Models\Setting;
use App\Services\HyundaiService;
use App\Services\LoadBalancerService;
use App\Services\P1MeterService;
use App\Services\PeblarService;
use App\Services\SolaxService;
use App\Services\ZonneplanService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ApiController extends Controller
{
    /**
     * Live status endpoint polled by the dashboard every 10 seconds.
     * Returns latest stored meter reading (fast) + live Peblar EV interface.
     * The scheduler (peblar:balance) handles slow polling of P1/Solax/Zonneplan.
     */
    public function status(PeblarService $peblar): JsonResponse
    {
        // Live Peblar data only (fast, ~100ms)
        $peblarData = $peblar->getAllData();

        // Latest stored reading from DB (written by scheduler)
        $latest  = MeterReading::latest('recorded_at')->first();
        $decision = ChargeDecision::latest('decided_at')->first();

        // Build p1/solax/price from latest stored reading
        $p1Data = null;
        $solaxData = null;
        $price = null;

        if ($latest) {
            $p1Data = [
                'online'          => $latest->p1_power_consumed !== null,
                'power_consumed_w' => $latest->p1_power_consumed,
                'power_produced_w' => $latest->p1_power_produced,
                'voltage_l1'      => $latest->p1_voltage_l1,
                'voltage_l2'      => $latest->p1_voltage_l2,
                'voltage_l3'      => $latest->p1_voltage_l3,
            ];
            $solaxData = [
                'online'          => $latest->solax_pv_power !== null,
                'pv_power_w'      => $latest->solax_pv_power,
                'battery_soc'     => $latest->solax_battery_soc,
                'battery_power_w' => $latest->solax_battery_power,
                'grid_power_w'    => $latest->solax_grid_power,
            ];
            $price = $latest->price_current ? (float) $latest->price_current : null;
        }

        // Voertuigdata uit meest recente reading MÉT vehicle data
        // (los van $latest, want balancer maakt elke minuut een nieuwe reading zonder vehicle kolommen)
        $vehicleReading = MeterReading::whereNotNull('vehicle_soc')
            ->latest('recorded_at')
            ->first();
        $vehicleData = $vehicleReading ? [
            'soc'               => $vehicleReading->vehicle_soc,
            'is_charging'       => (bool) $vehicleReading->vehicle_charging,
            'is_plugged_in'     => (bool) $vehicleReading->vehicle_plugged_in,
            'range_km'          => $vehicleReading->vehicle_range_km,
            'minutes_to_full'   => $vehicleReading->vehicle_minutes_to_full,
            'last_fetched_at'   => $vehicleReading->recorded_at?->toIso8601ZuluString(),
            'last_updated_at'   => $vehicleReading->vehicle_last_updated_at?->toIso8601ZuluString(),
            'polling_suspended' => (bool) Setting::get('hyundai_polling_suspended', false),
        ] : null;

        return response()->json([
            'peblar'           => $peblarData,
            'p1'               => $p1Data,
            'solax'            => $solaxData,
            'price'            => $price,
            'decision'         => $decision,
            'vehicle'          => $vehicleData,
            'balancer_enabled' => (bool) Setting::get('balancer_enabled', true),
            'force_charge'     => [
                'active'     => (bool) Setting::get('force_charge_active', false),
                'target_soc' => (int) Setting::get('force_charge_target_soc', 100),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Manually trigger one balancer cycle
     */
    public function balance(LoadBalancerService $balancer): JsonResponse
    {
        $decision = $balancer->run();
        return response()->json($decision);
    }

    /**
     * Manually override charge current (bypass balancer temporarily)
     */
    public function setChargeOverride(Request $request, PeblarService $peblar): JsonResponse
    {
        $maxA     = (int) Setting::get('max_charge_current_a', 13);
        $maxPhases = (int) Setting::get('phase_count', 3);

        $validated = $request->validate([
            'current_a' => "required|numeric|min:0|max:{$maxA}",
            'phases'    => "sometimes|integer|in:1,3",
        ]);

        $ma     = (int) round($validated['current_a'] * 1000);
        $phases = (int) ($validated['phases'] ?? $maxPhases);

        // Clamp phases to what the installation supports
        $phases = min($phases, $maxPhases);

        // Bepaal huidig aantal fases zodat we alleen omschakelen indien nodig.
        $peblarData    = $peblar->getAllData();
        $force1Phase   = (bool) ($peblarData['evinterface']['Force1Phase'] ?? false);
        $currentPhases = $force1Phase ? 1 : $maxPhases;

        if ($phases !== $currentPhases) {
            // Bij opschalen (1→3): stop eerst het laden zodat de auto opnieuw
            // onderhandelt over het aantal fases (zelfde patroon als in LoadBalancerService).
            if ($phases > $currentPhases) {
                $peblar->setChargeCurrentLimit(0);
            }
            $peblar->setPhaseCount($phases);
        }

        $ok = $peblar->setChargeCurrentLimit($ma);

        // Vergrendel de balancer voor 30 minuten zodat hij de override niet overschrijft
        $until = now()->addMinutes(30)->toDateTimeString();
        Setting::set('override_until', $until);
        Setting::set('override_current_ma', (string) $ma);

        return response()->json(['success' => $ok, 'current_ma' => $ma, 'phases' => $phases, 'override_until' => $until]);
    }

    /**
     * Activate "force charge" mode: charge at max until target SoC is reached.
     * Survives balancer cycles until SoC target is met or manually stopped.
     */
    public function setForceCharge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_soc' => 'required|integer|min:1|max:100',
        ]);

        Setting::set('force_charge_active', '1');
        Setting::set('force_charge_target_soc', (string) $validated['target_soc']);

        // Clear any timed override so force-charge takes precedence
        Setting::set('override_until', '');

        return response()->json([
            'active'     => true,
            'target_soc' => $validated['target_soc'],
        ]);
    }

    /**
     * Stop "force charge" mode, hand control back to the balancer.
     * Stuurt direct een stop-commando zodat het laden ook echt eindigt;
     * balancer beslist volgende cyclus pas weer.
     */
    public function stopForceCharge(PeblarService $peblar): JsonResponse
    {
        Setting::set('force_charge_active', '0');
        $peblar->setChargeCurrentLimit(0);

        return response()->json(['active' => false]);
    }

    /**
     * Toggle load balancer on/off
     */
    public function toggleBalancer(Request $request): JsonResponse
    {
        $current = Setting::get('balancer_enabled', true);
        Setting::set('balancer_enabled', !$current ? '1' : '0');
        return response()->json(['enabled' => !$current]);
    }

    /**
     * Force-refresh voertuigstatus via BlueLink (omzeilt cache)
     */
    public function vehicleRefresh(HyundaiService $hyundai): JsonResponse
    {
        if (!$hyundai->isConfigured()) {
            return response()->json(['error' => 'BlueLink niet geconfigureerd'], 422);
        }

        $data = $hyundai->getData(forceRefresh: true, live: true);

        if (!$data) {
            return response()->json(['error' => 'Geen data ontvangen — auto offline of API fout'], 503);
        }

        // Sla op in de laatste MeterReading
        $latest = MeterReading::latest('recorded_at')->first();
        if ($latest) {
            $latest->update([
                'vehicle_soc'             => $data['soc'],
                'vehicle_charging'        => $data['is_charging'],
                'vehicle_plugged_in'      => $data['is_plugged_in'],
                'vehicle_range_km'        => $data['range_km'],
                'vehicle_minutes_to_full' => $data['minutes_to_full'],
                'vehicle_last_updated_at' => !empty($data['last_updated']) ? $data['last_updated'] : null,
            ]);
        }

        return response()->json([
            'soc'              => $data['soc'],
            'is_charging'      => $data['is_charging'],
            'is_plugged_in'    => $data['is_plugged_in'],
            'range_km'         => $data['range_km'],
            'minutes_to_full'  => $data['minutes_to_full'],
            'last_fetched_at'  => now()->toIso8601ZuluString(),
            'last_updated_at'  => $data['last_updated'] ?? null,
            'vehicle_name'     => $data['vehicle_name'] ?? null,
        ]);
    }

    /**
     * Aggregated history stats for a date range.
     * GET /api/history?from=YYYY-MM-DD&to=YYYY-MM-DD
     * Returns: solar_kwh, grid_kwh, solar_pct, grid_pct, total_cost_eur, total_kwh
     */
    public function history(Request $request): JsonResponse
    {
        if ($request->filled('hours')) {
            $hours = max(1, (int) $request->input('hours'));
            $from  = now()->subHours($hours);
            $to    = now();
        } else {
            $from = $request->filled('from')
                ? Carbon::parse($request->input('from'))->startOfDay()
                : now()->startOfDay();
            $to = $request->filled('to')
                ? Carbon::parse($request->input('to'))->endOfDay()
                : now();
        }

        $readings = MeterReading::where('recorded_at', '>=', $from)
            ->where('recorded_at', '<=', $to)
            ->orderBy('recorded_at')
            ->get(['recorded_at', 'peblar_energy_total', 'peblar_power_total',
                   'p1_power_consumed', 'p1_power_produced', 'price_current']);

        $solarWh = 0.0;
        $gridWh  = 0.0;
        $costEur = 0.0;
        $prevEnergy = null;

        foreach ($readings as $r) {
            $energy = $r->peblar_energy_total;
            if ($prevEnergy !== null && $energy !== null) {
                $deltaWh     = max(0, $energy - $prevEnergy);
                $peblarW     = max(0, (float) ($r->peblar_power_total ?? 0));
                $gridNetW    = (float) ($r->p1_power_consumed ?? 0) - (float) ($r->p1_power_produced ?? 0);
                $solarShareW = max(0, $peblarW - $gridNetW);
                $solarFrac   = $peblarW > 0 ? min(1.0, $solarShareW / $peblarW) : 0.0;
                $rowSolar    = $deltaWh * $solarFrac;
                $rowGrid     = $deltaWh - $rowSolar;
                $solarWh += $rowSolar;
                $gridWh  += $rowGrid;
                $costEur += ($rowGrid / 1000.0) * (float) ($r->price_current ?? 0);
            }
            if ($energy !== null) {
                $prevEnergy = $energy;
            }
        }

        $totalWh  = $solarWh + $gridWh;
        $solarPct = $totalWh > 0 ? round($solarWh / $totalWh * 100, 1) : 0.0;

        return response()->json([
            'solar_kwh'      => round($solarWh / 1000, 2),
            'grid_kwh'       => round($gridWh / 1000, 2),
            'solar_pct'      => $solarPct,
            'grid_pct'       => $totalWh > 0 ? round(100 - $solarPct, 1) : 0.0,
            'total_cost_eur' => round($costEur, 2),
            'total_kwh'      => round($totalWh / 1000, 2),
        ]);
    }

    /**
     * Live chart data for history page
     */
    public function chartData(Request $request): JsonResponse
    {
        $hours = min((int) $request->input('hours', 24), 168);
        $since = now()->subHours($hours);

        $readings = MeterReading::where('recorded_at', '>=', $since)
            ->orderBy('recorded_at')
            ->get(['recorded_at', 'peblar_power_total', 'p1_power_consumed', 'p1_power_produced',
                   'solax_pv_power', 'price_current', 'peblar_charge_current_actual']);

        return response()->json($readings);
    }
}
