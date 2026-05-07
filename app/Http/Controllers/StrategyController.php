<?php

namespace App\Http\Controllers;

use App\Models\MeterReading;
use App\Models\Setting;
use App\Services\ChargePlanService;

class StrategyController extends Controller
{
    public function index(ChargePlanService $planner)
    {
        $summary         = $planner->getSummary();
        $batteryCapacity = (float) Setting::get('battery_capacity_kwh', 60);
        $defaultTarget   = (int)   Setting::get('default_target_soc', 90);
        $priceThresholdCt = (int) round((float) Setting::get('price_threshold', 0.22) * 100);

        // Voertuigdata altijd uit DB — nooit live call op paginabezoek
        $latestVehicle = MeterReading::whereNotNull('vehicle_soc')
            ->latest('recorded_at')
            ->first();
        $autoSoc     = $latestVehicle?->vehicle_soc;
        $vehicleData = $latestVehicle ? [
            'soc'              => $latestVehicle->vehicle_soc,
            'is_charging'      => (bool) $latestVehicle->vehicle_charging,
            'is_plugged_in'    => (bool) $latestVehicle->vehicle_plugged_in,
            'range_km'         => $latestVehicle->vehicle_range_km,
            'minutes_to_full'  => $latestVehicle->vehicle_minutes_to_full,
            'recorded_at'      => $latestVehicle->recorded_at,
        ] : null;

        return view('strategy', compact(
            'summary', 'batteryCapacity', 'defaultTarget', 'vehicleData', 'autoSoc', 'priceThresholdCt'
        ));
    }
}
