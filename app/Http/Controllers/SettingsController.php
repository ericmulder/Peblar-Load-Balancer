<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\SolarForecastService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->groupBy('group');
        return view('settings', compact('settings'));
    }

    public function update(Request $request, SolarForecastService $solar)
    {
        $keys = Setting::pluck('key')->toArray();

        foreach ($keys as $key) {
            if ($request->has($key)) {
                Setting::set($key, $request->input($key));
            }
        }

        // Als solar forecast aan staat: direct een verse forecast ophalen
        // (loopt synchroon maar is snel — één HTTP-call naar Open-Meteo)
        if ($solar->isEnabled() && $solar->isConfigured()) {
            try {
                Artisan::call('peblar:fetch-solar');
            } catch (\Throwable) {
                // Stilletjes falen — de dagelijkse scheduler pikt het op
            }
        }

        return redirect()->route('settings.index')->with('success', 'Instellingen opgeslagen.');
    }
}
