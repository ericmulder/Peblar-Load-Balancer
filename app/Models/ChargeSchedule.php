<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ChargeSchedule extends Model
{
    protected $fillable = [
        'label', 'day_of_week', 'deadline_time',
        'target_soc', 'min_soc', 'plan_ahead_hours', 'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    const DAY_LABELS = [
        1 => 'Maandag',
        2 => 'Dinsdag',
        3 => 'Woensdag',
        4 => 'Donderdag',
        5 => 'Vrijdag',
        6 => 'Zaterdag',
        7 => 'Zondag',
    ];

    const DAY_SHORT = [
        1 => 'Ma', 2 => 'Di', 3 => 'Wo',
        4 => 'Do', 5 => 'Vr', 6 => 'Za', 7 => 'Zo',
    ];

    public function getDayLabelAttribute(): string
    {
        return $this->day_of_week
            ? (self::DAY_LABELS[$this->day_of_week] ?? '?')
            : 'Elke dag';
    }

    public function getDeadlineShortAttribute(): string
    {
        return substr($this->deadline_time, 0, 5);
    }

    /**
     * Volgende eerstkomende deadline als Carbon-object (Amsterdam-tijd).
     */
    public function nextOccurrence(): Carbon
    {
        $tz  = 'Europe/Amsterdam';
        $now = Carbon::now($tz);

        [$h, $m] = explode(':', $this->deadline_time);

        if ($this->day_of_week === null) {
            // Elke dag: vandaag of morgen
            $candidate = $now->copy()->setTime((int) $h, (int) $m, 0);
            return $candidate->isPast() ? $candidate->addDay() : $candidate;
        }

        // Specifieke dag van de week (1=ma … 7=zo)
        $candidate = $now->copy()->setTime((int) $h, (int) $m, 0);
        $targetDow = $this->day_of_week; // Carbon: 1=ma … 7=zo

        while ($candidate->dayOfWeekIso !== $targetDow || $candidate->isPast()) {
            $candidate->addDay();
        }

        return $candidate;
    }

    /**
     * Is de eerstvolgende deadline binnen $hours uur?
     */
    public function isDueWithin(int $hours): bool
    {
        return $this->nextOccurrence()->diffInHours(Carbon::now('Europe/Amsterdam')) <= $hours;
    }
}
