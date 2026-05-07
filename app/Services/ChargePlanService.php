<?php

namespace App\Services;

use App\Models\ChargeGoal;
use App\Models\MeterReading;
use App\Models\PriceForecast;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ChargePlanService
{
    // Max laadvermogen bij 13A 3-fase (kW)
    const MAX_CHARGE_KW = 9.0;

    /**
     * Bereken het optimale laadplan voor het actieve laaddoel.
     *
     * Geeft een array van uur-slots terug, gesorteerd op tijd:
     * [
     *   'hour'     => Carbon,
     *   'hour_iso' => string (ISO8601 UTC),
     *   'price_ct' => float,
     *   'action'   => 'cheap'|'planned'|'skip',
     *   'reason'   => string,
     *   'kwh'      => float,    // te laden in dit uur
     * ]
     */
    public function buildPlan(?ChargeGoal $goal = null): array
    {
        $goal ??= ChargeGoal::active();

        if (!$goal) {
            return [];
        }

        $remainingKwh  = $goal->remainingKwh();
        $hoursUntilDep = $goal->hoursUntilDepart();

        if ($remainingKwh <= 0 || $hoursUntilDep <= 0) {
            return [];
        }

        // Haal prijzen op tot aan vertrek
        $prices = PriceForecast::where('hour', '>=', now()->startOfHour())
            ->where('hour', '<', $goal->depart_at)
            ->orderBy('hour')
            ->get();

        if ($prices->isEmpty()) {
            return [];
        }

        // Minimaal benodigde laaduren (afgerond omhoog)
        $minHoursNeeded = (int) ceil($remainingKwh / self::MAX_CHARGE_KW);

        // Drempelprijs uit instellingen (€/kWh → ct/kWh)
        $cheapThresholdCt = (float) Setting::get('price_threshold', 0.22) * 100;

        // Bouw kandidaat-uren
        $slots = $prices->map(function ($p) {
            return [
                'hour'     => $p->hour,
                'hour_iso' => $p->hour->toIso8601ZuluString(),
                'price_ct' => round($p->price_eur_incl_tax * 100, 2),
                'action'   => 'skip',
                'reason'   => '',
                'kwh'      => 0.0,
            ];
        })->values()->toArray();

        $remainingToFill = $remainingKwh;

        // Stap 1: selecteer de goedkoopste N uren
        $indexed = collect($slots)->map(fn($s, $i) => array_merge($s, ['idx' => $i]));
        $needed  = $indexed->sortBy('price_ct')->take($minHoursNeeded);

        foreach ($needed as $item) {
            $slots[$item['idx']]['action'] = 'planned';
            $slots[$item['idx']]['reason'] = 'Gepland: één van de ' . $minHoursNeeded . ' goedkoopste uren';
        }

        // Stap 2: markeer extra goedkope uren buiten het plan
        foreach ($slots as &$slot) {
            if ($slot['action'] === 'skip' && $slot['price_ct'] <= $cheapThresholdCt) {
                $slot['action'] = 'cheap';
                $slot['reason'] = 'Goedkoop tarief (≤' . round($cheapThresholdCt, 1) . 'ct)';
            }
        }
        unset($slot);

        // Stap 3: vul kWh per uur — planned eerst, daarna cheap
        $priorityOrder = ['planned' => 0, 'cheap' => 1];
        $fillOrder = collect($slots)
            ->map(fn($s, $i) => array_merge($s, ['idx' => $i]))
            ->filter(fn($s) => $s['action'] !== 'skip')
            ->sortBy([
                fn($a, $b) => ($priorityOrder[$a['action']] ?? 9) <=> ($priorityOrder[$b['action']] ?? 9),
                fn($a, $b) => $a['hour_iso'] <=> $b['hour_iso'],
            ]);

        foreach ($fillOrder as $item) {
            if ($remainingToFill <= 0) break;
            $kwh = round(min(self::MAX_CHARGE_KW, $remainingToFill), 2);
            $slots[$item['idx']]['kwh'] = $kwh;
            $remainingToFill = max(0, $remainingToFill - $kwh);
        }

        return $slots;
    }

    /**
     * Bepaal of het huidige uur een laaduur is volgens het plan.
     * Geeft ['should_charge' => bool, 'reason' => string] terug.
     */
    public function currentHourDecision(): array
    {
        $goal = ChargeGoal::active();

        if (!$goal) {
            return ['should_charge' => false, 'reason' => 'Geen actief laaddoel'];
        }

        if ($goal->remainingKwh() <= 0) {
            return ['should_charge' => false, 'reason' => 'Laaddoel al bereikt'];
        }

        // Spoedsituatie: te weinig uren over om doel te halen → altijd laden
        $hoursLeft    = $goal->hoursUntilDepart();
        $remainingKwh = $goal->remainingKwh();
        $minHoursNeeded = ceil($remainingKwh / self::MAX_CHARGE_KW);

        if ($hoursLeft <= $minHoursNeeded * 1.2) {
            return [
                'should_charge' => true,
                'reason'        => sprintf('Spoed: nog %.1fu voor vertrek, %.1f kWh nodig', $hoursLeft, $remainingKwh),
            ];
        }

        // Zoek dit uur in het plan
        $currentHour = now()->startOfHour();
        $plan = $this->buildPlan($goal);

        foreach ($plan as $slot) {
            if (Carbon::parse($slot['hour_iso'])->eq($currentHour)) {
                $charge = $slot['action'] !== 'skip';
                return [
                    'should_charge' => $charge,
                    'reason'        => $slot['reason'] ?: 'Niet geselecteerd in plan',
                ];
            }
        }

        return ['should_charge' => false, 'reason' => 'Uur niet in plan'];
    }

    /**
     * Bepaal of het huidige uur het beste moment is om te laden door de goedkoopste uren
     * in een venster van N uur vooruit te vergelijken met het huidig uur.
     *
     * Berekent hoeveel laaduren nodig zijn op basis van SoC-verschil en selecteert de
     * goedkoopste N uren in het venster. Laad alleen als het huidige uur ertoe behoort.
     */
    public function cheapHourDecision(int $windowHours = 8): array
    {
        $batteryKwh = (float) Setting::get('battery_capacity_kwh', 60);
        $currentSoc = MeterReading::whereNotNull('vehicle_soc')
            ->latest('recorded_at')
            ->value('vehicle_soc');

        $goal      = ChargeGoal::active();
        $targetSoc = $goal?->target_soc ?? (int) Setting::get('default_target_soc', 90);

        if ($currentSoc === null) {
            return ['should_charge' => true, 'reason' => 'Geen SoC-data beschikbaar'];
        }

        if ($currentSoc >= $targetSoc) {
            return ['should_charge' => false, 'reason' => "SoC {$currentSoc}% ≥ doel {$targetSoc}%"];
        }

        $energyNeededKwh = ($targetSoc - $currentSoc) / 100 * $batteryKwh;
        $hoursNeeded     = max(1, (int) ceil($energyNeededKwh / self::MAX_CHARGE_KW));

        $currentHour = now()->startOfHour();
        $forecasts   = PriceForecast::where('hour', '>=', $currentHour)
            ->where('hour', '<', $currentHour->copy()->addHours($windowHours))
            ->orderBy('price_eur_incl_tax')
            ->get();

        if ($forecasts->isEmpty()) {
            return ['should_charge' => true, 'reason' => 'Geen prijsdata beschikbaar'];
        }

        $cheapestN     = min($hoursNeeded, $forecasts->count());
        $cheapestHours = $forecasts->take($cheapestN)
            ->map(fn($f) => Carbon::parse($f->hour)->startOfHour());

        $isCurrentCheapest = $cheapestHours->contains(fn($h) => $h->eq($currentHour));

        if ($isCurrentCheapest) {
            return [
                'should_charge' => true,
                'reason'        => sprintf(
                    'Goedkoopste %d/%du (SoC %d%%→%d%%, %.1f kWh)',
                    $cheapestN, $forecasts->count(), $currentSoc, $targetSoc, $energyNeededKwh
                ),
            ];
        }

        $cheapest     = $forecasts->first();
        $cheapestTime = Carbon::parse($cheapest->hour)->setTimezone('Europe/Amsterdam');

        return [
            'should_charge' => false,
            'reason'        => sprintf(
                'Goedkoper uur om %s (€%.3f/kWh) — SoC %d%%, nog %.1f kWh nodig',
                $cheapestTime->format('H:i'),
                $cheapest->price_eur_incl_tax,
                $currentSoc,
                $energyNeededKwh
            ),
        ];
    }

    /**
     * Samenvatting voor de strategy-pagina.
     */
    public function getSummary(): array
    {
        $goal = ChargeGoal::active();

        if (!$goal) {
            return ['has_goal' => false];
        }

        $plan          = $this->buildPlan($goal);
        $plannedSlots  = collect($plan)->where('action', '!=', 'skip');
        $totalKwh      = $plannedSlots->sum('kwh');
        $avgPrice      = $plannedSlots->avg('price_ct') ?? 0;
        $estimatedCost = $plannedSlots->sum(fn($s) => $s['kwh'] * $s['price_ct'] / 100);

        return [
            'has_goal'       => true,
            'goal'           => $goal,
            'plan'           => $plan,
            'planned_hours'  => $plannedSlots->count(),
            'total_kwh'      => round($totalKwh, 2),
            'avg_price_ct'   => round($avgPrice, 1),
            'estimated_cost' => round($estimatedCost, 2),
            'remaining_kwh'  => $goal->remainingKwh(),
            'hours_until_dep'=> round($goal->hoursUntilDepart(), 1),
            'progress_pct'   => $goal->progressPercent(),
        ];
    }
}
