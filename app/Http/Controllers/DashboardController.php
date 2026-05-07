<?php

namespace App\Http\Controllers;

use App\Models\ChargeDecision;
use App\Models\ChargeGoal;
use App\Models\MeterReading;
use App\Models\Setting;
use App\Services\ZonneplanService;

class DashboardController extends Controller
{
    public function __invoke(ZonneplanService $zonneplan)
    {
        $latest        = MeterReading::latest('recorded_at')->first();
        $lastDecision  = ChargeDecision::latest('decided_at')->first();
        $forecast      = $zonneplan->getForecast();
        $activeGoal    = ChargeGoal::active();
        $defaultTarget = (int) Setting::get('default_target_soc', 90);

        // Voertuigdata: altijd uit DB, nooit live (Python wordt alleen aangeroepen
        // via de scheduler als auto ingeplugd is, of via de "Vernieuwen"-knop)
        $latestVehicle = MeterReading::whereNotNull('vehicle_soc')
            ->latest('recorded_at')
            ->first();
        $autoSoc = $latestVehicle?->vehicle_soc ?? $latest?->vehicle_soc;

        return view('dashboard', compact(
            'latest', 'lastDecision', 'forecast',
            'activeGoal', 'defaultTarget', 'autoSoc'
        ));
    }
}
