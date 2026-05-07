<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChargeGoal extends Model
{
    protected $fillable = [
        'depart_at', 'target_soc', 'current_soc',
        'energy_needed_kwh', 'energy_added_kwh', 'active',
    ];

    protected $casts = [
        'depart_at'        => 'datetime',
        'active'           => 'boolean',
        'energy_needed_kwh'=> 'float',
        'energy_added_kwh' => 'float',
    ];

    public static function active(): ?self
    {
        return static::where('active', true)
            ->where('depart_at', '>', now())
            ->orderBy('depart_at')
            ->first();
    }

    public function progressPercent(): float
    {
        if ($this->energy_needed_kwh <= 0) return 100;
        return min(100, round($this->energy_added_kwh / $this->energy_needed_kwh * 100, 1));
    }

    public function remainingKwh(): float
    {
        return max(0, $this->energy_needed_kwh - $this->energy_added_kwh);
    }

    public function hoursUntilDepart(): float
    {
        return max(0, now()->diffInMinutes($this->depart_at) / 60);
    }
}
