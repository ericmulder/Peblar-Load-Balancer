<?php

namespace App\Http\Controllers;

use App\Models\ChargeGoal;
use App\Models\MeterReading;
use App\Models\Setting;
use Illuminate\Http\Request;

class ChargeGoalController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'depart_at'   => 'required|date|after:now',
            'current_soc' => 'nullable|integer|min:0|max:100',
            'target_soc'  => 'required|integer|min:1|max:100',
        ]);

        // Haal SoC uit DB (nooit live call — scheduler/vernieuwen-knop doet dat)
        $currentSoc = $data['current_soc'] ?? null;

        if ($currentSoc === null) {
            $currentSoc = MeterReading::whereNotNull('vehicle_soc')
                ->latest('recorded_at')
                ->value('vehicle_soc');
        }

        if ($currentSoc === null) {
            return back()->withErrors(['current_soc' => 'Huidige SoC is vereist (BlueLink niet beschikbaar).'])->withInput();
        }

        // Deactiveer vorige doelen
        ChargeGoal::where('active', true)->update(['active' => false]);

        $capacity     = (float) Setting::get('battery_capacity_kwh', 60);
        $socDiff      = max(0, $data['target_soc'] - $currentSoc);
        $energyNeeded = round($socDiff / 100 * $capacity, 2);

        ChargeGoal::create([
            'depart_at'         => $data['depart_at'],
            'target_soc'        => $data['target_soc'],
            'current_soc'       => $currentSoc,
            'energy_needed_kwh' => $energyNeeded,
            'energy_added_kwh'  => 0,
            'active'            => true,
        ]);

        $msg = 'Laaddoel opgeslagen.';
        if (!$data['current_soc'] && $currentSoc) {
            $msg .= ' SoC automatisch ingevuld uit laatste bekende stand (' . $currentSoc . '%).';
        }

        return redirect()->route('strategy')->with('success', $msg);
    }

    public function destroy(ChargeGoal $goal)
    {
        $goal->update(['active' => false]);
        return redirect()->route('strategy')->with('success', 'Laaddoel geannuleerd.');
    }
}
