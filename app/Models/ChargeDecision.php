<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChargeDecision extends Model
{
    protected $fillable = [
        'decided_at', 'priority', 'priority_label',
        'desired_power_w', 'charge_current_ma', 'previous_current_ma',
        'reason', 'price_eur', 'solar_surplus_w',
        'household_consumption_w', 'available_grid_w', 'command_sent',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
        'command_sent' => 'boolean',
    ];

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            4 => 'red',
            3 => 'orange',
            2 => 'yellow',
            1 => 'blue',
            default => 'gray',
        };
    }
}
