<?php

namespace App\Http\Controllers;

use App\Models\ChargeGoal;
use App\Models\ChargeSchedule;
use App\Models\MeterReading;
use App\Models\Setting;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index()
    {
        $schedules = ChargeSchedule::orderBy('day_of_week')->orderBy('deadline_time')->get();

        // Voeg nextOccurrence toe voor weergave
        $schedules->each(fn ($s) => $s->next = $s->nextOccurrence());

        return view('schedule', compact('schedules'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label'            => 'nullable|string|max:100',
            'day_of_week'      => 'nullable|integer|between:1,7',
            'deadline_time'    => 'required|date_format:H:i',
            'target_soc'       => 'required|integer|between:1,100',
            'min_soc'          => 'nullable|integer|between:1,100',
            'plan_ahead_hours' => 'required|integer|between:1,72',
            'active'           => 'boolean',
        ]);

        $data['active'] = $request->boolean('active', true);
        ChargeSchedule::create($data);

        return redirect()->route('schedule.index')->with('success', 'Laadmoment toegevoegd.');
    }

    public function update(Request $request, ChargeSchedule $schedule)
    {
        $data = $request->validate([
            'label'            => 'nullable|string|max:100',
            'day_of_week'      => 'nullable|integer|between:1,7',
            'deadline_time'    => 'required|date_format:H:i',
            'target_soc'       => 'required|integer|between:1,100',
            'min_soc'          => 'nullable|integer|between:1,100',
            'plan_ahead_hours' => 'required|integer|between:1,72',
            'active'           => 'boolean',
        ]);

        $data['active'] = $request->boolean('active', true);
        $schedule->update($data);

        return redirect()->route('schedule.index')->with('success', 'Laadmoment bijgewerkt.');
    }

    public function destroy(ChargeSchedule $schedule)
    {
        $schedule->delete();
        return redirect()->route('schedule.index')->with('success', 'Laadmoment verwijderd.');
    }

    public function toggle(ChargeSchedule $schedule)
    {
        $schedule->update(['active' => !$schedule->active]);
        return response()->json(['active' => $schedule->active]);
    }

    /**
     * Activeer het eerstvolgende schema-item als laaddoel nu.
     */
    public function activate(ChargeSchedule $schedule)
    {
        $next       = $schedule->nextOccurrence();
        $currentSoc = MeterReading::whereNotNull('vehicle_soc')->latest('recorded_at')->value('vehicle_soc') ?? 20;
        $capacity   = (float) Setting::get('battery_capacity_kwh', 60);
        $socDiff    = max(0, $schedule->target_soc - $currentSoc);

        ChargeGoal::where('active', true)->update(['active' => false]);

        ChargeGoal::create([
            'depart_at'         => $next->utc(),
            'target_soc'        => $schedule->target_soc,
            'current_soc'       => $currentSoc,
            'energy_needed_kwh' => round($socDiff / 100 * $capacity, 2),
            'energy_added_kwh'  => 0,
            'active'            => true,
        ]);

        return redirect()->route('strategy')->with('success',
            'Laaddoel aangemaakt: ' . $next->setTimezone('Europe/Amsterdam')->format('D d M H:i') . ' → ' . $schedule->target_soc . '%'
        );
    }
}
