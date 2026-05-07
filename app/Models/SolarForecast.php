<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolarForecast extends Model
{
    protected $fillable = ['hour', 'wh_expected', 'gti_wm2', 'fetched_at'];

    protected $casts = [
        'hour'       => 'datetime',
        'fetched_at' => 'datetime',
    ];

    /**
     * Verwachte opwekking (Wh) voor het huidige uur. Null als geen forecast beschikbaar.
     */
    public static function currentHourWh(): ?float
    {
        return static::where('hour', now()->startOfHour())->value('wh_expected');
    }
}
