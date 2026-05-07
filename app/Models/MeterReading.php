<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeterReading extends Model
{
    protected $fillable = [
        'recorded_at',
        'peblar_power_total', 'peblar_power_l1', 'peblar_power_l2', 'peblar_power_l3',
        'peblar_current_l1', 'peblar_current_l2', 'peblar_current_l3',
        'peblar_voltage_l1', 'peblar_voltage_l2', 'peblar_voltage_l3',
        'peblar_energy_session', 'peblar_energy_total',
        'peblar_cp_state', 'peblar_charge_current_limit', 'peblar_charge_current_actual',
        'p1_power_consumed', 'p1_power_produced',
        'p1_voltage_l1', 'p1_voltage_l2', 'p1_voltage_l3',
        'solax_pv_power', 'solax_battery_soc', 'solax_battery_power', 'solax_grid_power',
        'price_current',
        'vehicle_soc', 'vehicle_charging', 'vehicle_plugged_in',
        'vehicle_range_km', 'vehicle_minutes_to_full', 'vehicle_last_updated_at',
    ];

    protected $casts = [
        'recorded_at'            => 'datetime',
        'vehicle_last_updated_at' => 'datetime',
    ];
}
