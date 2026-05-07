<?php

namespace App\Services;

use App\Models\ChargeDecision;
use App\Models\ChargeGoal;
use App\Models\ChargeSchedule;
use App\Models\MeterReading;
use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class LoadBalancerService
{
    const PRIORITY_STOP   = 0;
    const PRIORITY_LOW    = 1;
    const PRIORITY_NORMAL = 2;
    const PRIORITY_HIGH   = 3;
    const PRIORITY_URGENT = 4;

    const PRIORITY_LABELS = [
        0 => 'Stop',
        1 => 'Laag',
        2 => 'Normaal',
        3 => 'Hoog',
        4 => 'Urgent',
    ];

    // Minimum charge current in mA (below this Peblar stops charging)
    const MIN_CHARGE_CURRENT_MA = 6000;

    // Minimum power for 1-phase charging: 6A × 230V = 1,380W (IEC 61851)
    // When solar surplus triggers charging but falls short of this, the deficit
    // is pulled from the grid so the charger can reach the protocol minimum.
    const MIN_SOLAR_CHARGE_W = 1380;

    public function __construct(
        private PeblarService $peblar,
        private P1MeterService $p1,
        private SolaxService $solax,
        private ZonneplanService $zonneplan,
        private ChargePlanService $planner,
    ) {}

    /**
     * Main entry point: decide charge current and send to Peblar
     */
    public function run(): ChargeDecision
    {
        $enabled = Setting::get('balancer_enabled', true);

        // Collect all sensor data
        $peblarData = $this->peblar->getAllData();
        $p1Data     = $this->p1->getData();
        $solaxData  = $this->solax->getData();
        $price      = $this->zonneplan->getCurrentPrice();

        // Store meter reading + update charge goal progress
        $energySessionWh = $peblarData['meter']['EnergySession'] ?? 0;
        $cpState         = $peblarData['evinterface']['CpState'] ?? 'State A';
        $justPluggedIn   = $this->detectPlugIn($cpState);
        $this->storeMeterReading($peblarData, $p1Data, $solaxData, $price);
        $goalJustCompleted = $this->updateGoalProgress($energySessionWh);

        // Auto-create a ChargeGoal from schedule if deadline is approaching
        $this->syncScheduleGoal();

        // Forceer voertuigdata ophalen zodra auto wordt ingeplugd
        if ($justPluggedIn) {
            Log::info('Auto ingeplugd (CpState: State A → ' . $cpState . ') — voertuigdata forceren');
            Artisan::call('peblar:fetch-vehicle', ['--force' => true]);
        }

        // Get current charge current (what Peblar is actually doing)
        $currentMa = $peblarData['evinterface']['ChargeCurrentLimitActual'] ?? 0;

        // Laaddoel net bereikt — stop direct, laat normale balancer niet ingrijpen.
        if ($goalJustCompleted) {
            return $this->recordDecision(
                priority: self::PRIORITY_STOP,
                desiredPowerW: 0,
                currentMa: $currentMa,
                newMa: 0,
                reason: 'Laaddoel bereikt — laden gestopt',
                price: $price,
                solarSurplus: null,
                householdConsumption: null,
                availableGrid: null,
                commandSent: true,
            );
        }

        if (!$enabled) {
            return $this->recordDecision(
                priority: self::PRIORITY_STOP,
                desiredPowerW: 0,
                currentMa: $currentMa,
                newMa: 0,
                reason: 'Load balancer uitgeschakeld',
                price: $price,
                solarSurplus: null,
                householdConsumption: null,
                availableGrid: null,
                commandSent: false,
            );
        }

        // Force-charge modus: laad op vol netcapaciteit tot doel-SoC bereikt is.
        if (Setting::get('force_charge_active', false)) {
            $targetSoc  = (int) Setting::get('force_charge_target_soc', 100);
            $currentSoc = MeterReading::whereNotNull('vehicle_soc')
                ->latest('recorded_at')
                ->value('vehicle_soc');

            if ($currentSoc !== null && $currentSoc >= $targetSoc) {
                // Doel bereikt — zet force-charge uit
                Setting::set('force_charge_active', '0');
                Log::info("Force-charge klaar: SoC {$currentSoc}% ≥ doel {$targetSoc}%");
            } elseif ($cpState === 'State A') {
                // Auto losgekoppeld — zet force-charge uit
                Setting::set('force_charge_active', '0');
                Log::info('Force-charge gestopt: auto losgekoppeld');
            } else {
                // Nog actief: stuur max stroom
                $maxCurrentA   = (int) Setting::get('max_charge_current_a', 13);
                $maxPhases     = (int) Setting::get('phase_count', 3);
                $gridCapacityW = (int) Setting::get('grid_capacity_w', 17250);
                $gridBufferW   = (int) Setting::get('grid_buffer_w', 500);
                $p1Consumed    = $p1Data['power_consumed_w'] ?? 0;
                $p1Produced    = $p1Data['power_produced_w'] ?? 0;
                $peblarPower   = $peblarData['meter']['PowerTotal'] ?? 0;
                $solarPvW      = $solaxData['pv_power_w'] ?? 0;
                $batteryPower  = $solaxData['battery_power_w'] ?? 0;
                $gridNetW      = $p1Consumed - $p1Produced;
                $householdW    = max(0, $gridNetW + $solarPvW - max(0, $batteryPower) - $peblarPower);
                $availableGridW = min(
                    max(0, $gridCapacityW - $householdW - $gridBufferW),
                    $maxCurrentA * $maxPhases * 230
                );
                $forceMa = $this->powerToMilliamps($availableGridW, $maxPhases, $maxCurrentA);
                $socStr  = $currentSoc !== null ? "SoC {$currentSoc}% → {$targetSoc}%" : "SoC onbekend → {$targetSoc}%";

                // Force-charge moet altijd op max fases draaien. Bepaal huidig aantal
                // fases en schakel om indien nodig (zelfde patroon als in normale flow).
                $l1 = abs((float) ($peblarData['meter']['CurrentPhase1'] ?? 0));
                $l2 = abs((float) ($peblarData['meter']['CurrentPhase2'] ?? 0));
                $l3 = abs((float) ($peblarData['meter']['CurrentPhase3'] ?? 0));
                $force1PhaseFlag = (bool) ($peblarData['evinterface']['Force1Phase'] ?? false);
                $currentPhases = $l1 > 0.5
                    ? (($l2 > 0.5 || $l3 > 0.5) ? 3 : 1)
                    : ($force1PhaseFlag ? 1 : $maxPhases);

                $commandSent = false;
                if ($forceMa > 0 && $maxPhases !== $currentPhases) {
                    if ($maxPhases > $currentPhases) {
                        $this->peblar->setChargeCurrentLimit(0);
                    }
                    $this->peblar->setPhaseCount($maxPhases);
                    Log::info("Force-charge fase omgeschakeld: {$currentPhases} → {$maxPhases}");
                    $commandSent = $this->peblar->setChargeCurrentLimit($forceMa);
                } elseif (abs($forceMa - $currentMa) >= 500 || ($forceMa === 0 && $currentMa > 0)) {
                    $commandSent = $this->peblar->setChargeCurrentLimit($forceMa);
                }

                return $this->recordDecision(
                    priority: self::PRIORITY_URGENT,
                    desiredPowerW: $availableGridW,
                    currentMa: $currentMa,
                    newMa: $forceMa,
                    reason: "Force-charge: {$socStr}",
                    price: $price,
                    solarSurplus: null,
                    householdConsumption: $householdW,
                    availableGrid: $availableGridW,
                    commandSent: $commandSent,
                );
            }
        }

        // Handmatige override actief? Balancer niet laten ingrijpen tot de timer verloopt.
        $overrideUntil = Setting::get('override_until');
        if ($overrideUntil && now()->lt(\Carbon\Carbon::parse($overrideUntil))) {
            $overrideMa = (int) Setting::get('override_current_ma', 0);
            $remaining  = now()->diffInMinutes(\Carbon\Carbon::parse($overrideUntil));
            return $this->recordDecision(
                priority: self::PRIORITY_STOP,
                desiredPowerW: 0,
                currentMa: $currentMa,
                newMa: $overrideMa,
                reason: "Handmatige override actief (nog {$remaining} min)",
                price: $price,
                solarSurplus: null,
                householdConsumption: null,
                availableGrid: null,
                commandSent: false,
            );
        }

        // No car connected? Nothing to do
        $cpState = $peblarData['evinterface']['CpState'] ?? 'State A';
        if ($cpState === 'State A') {
            return $this->recordDecision(
                priority: self::PRIORITY_STOP,
                desiredPowerW: 0,
                currentMa: $currentMa,
                newMa: 0,
                reason: 'Geen auto aangesloten (State A)',
                price: $price,
                solarSurplus: null,
                householdConsumption: null,
                availableGrid: null,
                commandSent: false,
            );
        }

        // Settings
        $maxCurrentA    = (int) Setting::get('max_charge_current_a', 13);   // veiligheidsgrens per fase
        $gridCapacityW  = (int) Setting::get('grid_capacity_w', 17250);      // 25A × 3 fases × 230V
        $gridBufferW    = (int) Setting::get('grid_buffer_w', 500);
        $maxPhases      = (int) Setting::get('phase_count', 3);              // max fases (1 = enkelfasige installatie)
        $priceThreshold = (float) Setting::get('price_threshold', 0.22);
        $solarMinW      = (int) Setting::get('solar_min_surplus_w', 1500);
        $hysteresis     = (int) Setting::get('hysteresis_ma', 500);

        // Max laadvermogen afgeleid van ingestelde max stroom (A × max fases × 230V)
        $maxPowerW = $maxCurrentA * $maxPhases * 230;

        // Bepaal het huidige aantal actieve fases op basis van gemeten stromen.
        // evinterface.Phases reset naar 3 zodra laden stopt, ook al staat Force1Phase nog op true.
        // Force1Phase zelf blijft persistent wat wij instelden, en is de betrouwbare fallback
        // als er geen L1-stroom loopt (auto staat stil of herstart na fasewissel).
        $l1Current = abs((float) ($peblarData['meter']['CurrentPhase1'] ?? 0));
        $l2Current = abs((float) ($peblarData['meter']['CurrentPhase2'] ?? 0));
        $l3Current = abs((float) ($peblarData['meter']['CurrentPhase3'] ?? 0));
        $force1Phase   = (bool) ($peblarData['evinterface']['Force1Phase'] ?? false);
        $currentPhases = $l1Current > 0.5
            ? (($l2Current > 0.5 || $l3Current > 0.5) ? 3 : 1)
            : ($force1Phase ? 1 : $maxPhases);

        // Calculate household consumption (P1 net consumption = used - produced by solar)
        // P1 shows what's flowing through the meter, not house load
        $p1Consumed    = $p1Data['power_consumed_w'] ?? 0;
        $p1Produced    = $p1Data['power_produced_w'] ?? 0;
        $peblarPower   = $peblarData['meter']['PowerTotal'] ?? 0;

        // Household consumption = what house uses EXCLUDING the EV charger
        // Net P1 = grid_import (positive) or grid_export (negative)
        // House load = P1 net + solar_production
        $solarPvW      = $solaxData['pv_power_w'] ?? 0;
        $batteryPower  = $solaxData['battery_power_w'] ?? 0; // positive=charging

        // Net grid flow from P1: positive = importing, negative = exporting
        $gridNetW = $p1Consumed - $p1Produced;

        // Total house consumption including EV = gridNet + solar - battery_charging
        $totalConsumption = $gridNetW + $solarPvW - max(0, $batteryPower);

        // Household consumption excluding EV charger
        $householdW = max(0, $totalConsumption - $peblarPower);

        // Available grid capacity for EV charging
        $availableGridW = max(0, $gridCapacityW - $householdW - $gridBufferW);
        $availableGridW = min($availableGridW, $maxPowerW);

        // Zonne-overschot = deel van het laden dat door zon gedekt wordt.
        // Formule: laadvermogen minus netto netafname.
        //   netGrid > 0 (afname): surplus = max(0, peblar - netGrid)
        //   netGrid < 0 (teruglevering): surplus = peblar + |teruglevering|
        $solarSurplusW = max(0, (int) $peblarPower - $gridNetW);

        // Als er een actief laaddoel is: laat de planner beslissen over laden
        $planDecision = $this->planner->currentHourDecision();
        $activeGoal   = ChargeGoal::active();

        // Bepaal vermogen + reden
        if ($activeGoal && $planDecision['should_charge']) {
            // Plan selecteerde dit uur als optimaal laadmoment — laad op vol netcapaciteit.
            // De prijsdrempel geldt NIET hier: de planner heeft al de goedkoopste uren gekozen.
            $priority = self::PRIORITY_HIGH;
            $priceStr = $price !== null ? '€' . number_format($price, 3) . '/kWh' : 'prijs onbekend';
            $desiredW = $availableGridW;
            $reason   = '[Plan] Gepland uur (' . $priceStr . ') — laad op vol netcapaciteit';
        } elseif ($activeGoal && !$planDecision['should_charge']) {
            // Plan zegt dit uur overslaan — maar prijsdrempel overrulet het plan
            $priority = self::PRIORITY_LOW;
            [$desiredW, $decide] = $this->decide(
                priority: $priority,
                price: $price,
                priceThreshold: $priceThreshold,
                availableGridW: $availableGridW,
                solarSurplusW: $solarSurplusW,
                solarMinW: $solarMinW,
            );
            $reason = '[Plan] ' . $planDecision['reason'] . ' → ' . $decide;
        } else {
            $priority = $this->getCurrentPriority();
            [$desiredW, $reason] = $this->decide(
                priority: $priority,
                price: $price,
                priceThreshold: $priceThreshold,
                availableGridW: $availableGridW,
                solarSurplusW: $solarSurplusW,
                solarMinW: $solarMinW,
            );
        }

        // Bepaal gewenst aantal fases en converteer vermogen naar stroom
        $isGridCharging = $desiredW > 0 && $desiredW > $solarSurplusW;
        $desiredPhases  = $this->determinePhaseCount($desiredW, $solarSurplusW, $isGridCharging, $maxPhases, $currentPhases);

        // Convert power to current per phase (hard cap op max_charge_current_a)
        $newMa = $this->powerToMilliamps($desiredW, $desiredPhases, $maxCurrentA);

        // Schakel fases om vóór het instellen van de laadstroom (alleen bij actief laden)
        $commandSent = false;
        if ($newMa > 0 && $desiredPhases !== $currentPhases) {
            // Bij opschalen (1→3): stop eerst het laden zodat de auto opnieuw
            // onderhandelt over het aantal fases. Zonder stop blijft de auto op 1-fase
            // hangen ook al staat Force1Phase op false.
            if ($desiredPhases > $currentPhases) {
                $this->peblar->setChargeCurrentLimit(0);
            }
            $this->peblar->setPhaseCount($desiredPhases);
            $reason .= " [fase {$currentPhases}→{$desiredPhases}]";
            Log::info("Fase omgeschakeld: {$currentPhases} → {$desiredPhases} (surplus={$solarSurplusW}W, grid=" . ($isGridCharging ? 'ja' : 'nee') . ')');
            // Stroom altijd opnieuw instellen na fasewissel (bypass hysteresis)
            $commandSent = $this->peblar->setChargeCurrentLimit($newMa);
        } elseif (abs($newMa - $currentMa) >= $hysteresis || ($newMa === 0 && $currentMa > 0)) {
            // Geen fasewissel: normale hysteresis-check
            $commandSent = $this->peblar->setChargeCurrentLimit($newMa);
        }

        // Forceer voertuigdata als laden net is gestart (0 → >0)
        if ($newMa > 0 && $currentMa === 0) {
            Log::info('Laden gestart — voertuigdata forceren');
            Artisan::call('peblar:fetch-vehicle', ['--force' => true]);
        }

        return $this->recordDecision(
            priority: $priority,
            desiredPowerW: $desiredW,
            currentMa: $currentMa,
            newMa: $newMa,
            reason: $reason,
            price: $price,
            solarSurplus: $solarSurplusW,
            householdConsumption: $householdW,
            availableGrid: $availableGridW,
            commandSent: $commandSent,
        );
    }

    private function decide(
        int $priority,
        ?float $price,
        float $priceThreshold,
        int $availableGridW,
        int $solarSurplusW,
        int $solarMinW,
    ): array {
        $priceStr = $price !== null ? '€' . number_format($price, 3) . '/kWh' : 'prijs onbekend';

        if ($priority === self::PRIORITY_STOP) {
            return [0, 'Stop: schema geeft geen lading'];
        }

        if ($priority === self::PRIORITY_URGENT) {
            return [$availableGridW, "Urgent: laad op max beschikbaar vermogen ({$availableGridW}W, {$priceStr})"];
        }

        // Prijsdrempel: laad alleen als huidig uur bij de goedkoopste in het venster hoort
        if ($price !== null && $price <= $priceThreshold) {
            $cheapDecision = $this->planner->cheapHourDecision();
            if ($cheapDecision['should_charge']) {
                return [$availableGridW, "Prijs {$priceStr} ≤ €{$priceThreshold} ({$cheapDecision['reason']}) — laad op max ({$availableGridW}W)"];
            }
            return [0, "Prijs {$priceStr} goedkoop maar {$cheapDecision['reason']}"];
        }

        if ($solarSurplusW >= $solarMinW) {
            $w = $this->solarChargeW($solarSurplusW);
            $extra = $w > $solarSurplusW ? ' + ' . ($w - $solarSurplusW) . 'W net aanvulling' : '';
            return [$w, "Zon {$solarSurplusW}W overschot{$extra}"];
        }

        return [0, "Stop: prijs {$priceStr} te hoog en geen zonne-overschot ({$solarSurplusW}W < {$solarMinW}W)"];
    }

    /**
     * Geeft true als de auto NET ingeplugd is (vorige meting: State A, huidige: iets anders).
     */
    private function detectPlugIn(string $currentCpState): bool
    {
        if ($currentCpState === 'State A') {
            return false; // nog steeds geen auto
        }

        $prevState = MeterReading::latest('recorded_at')
            ->whereNotNull('peblar_cp_state')
            ->value('peblar_cp_state');

        return $prevState === 'State A';
    }

    /**
     * Controleer of een schema-item zijn plan_ahead_hours-venster is ingegaan.
     * Zo ja, maak automatisch een ChargeGoal aan (als er nog geen actief doel is).
     */
    private function syncScheduleGoal(): void
    {
        // Als er al een actief laaddoel is, niets doen
        if (ChargeGoal::active()) {
            return;
        }

        $now      = \Carbon\Carbon::now('Europe/Amsterdam');
        $capacity = (float) Setting::get('battery_capacity_kwh', 60);
        $currentSoc = MeterReading::whereNotNull('vehicle_soc')
            ->latest('recorded_at')
            ->value('vehicle_soc') ?? 0;

        // Zoek actieve schema's waarvan de eerstvolgende deadline binnen plan_ahead_hours valt
        $schedules = ChargeSchedule::where('active', true)->get();

        $best = null;
        $bestNext = null;

        foreach ($schedules as $schedule) {
            $next = $schedule->nextOccurrence();
            $hoursUntil = $now->diffInMinutes($next) / 60;

            if ($hoursUntil > $schedule->plan_ahead_hours) {
                continue; // deadline nog te ver weg
            }

            // Als huidig SoC al hoog genoeg is, sla over
            if ($currentSoc >= $schedule->target_soc) {
                continue; // doel al bereikt
            }

            // Kies het schema met de vroegste deadline
            if ($bestNext === null || $next->lt($bestNext)) {
                $best     = $schedule;
                $bestNext = $next;
            }
        }

        if (!$best || !$bestNext) {
            return;
        }

        $socDiff = max(0, $best->target_soc - $currentSoc);

        ChargeGoal::where('active', true)->update(['active' => false]);

        ChargeGoal::create([
            'depart_at'         => $bestNext->utc(),
            'target_soc'        => $best->target_soc,
            'current_soc'       => $currentSoc,
            'energy_needed_kwh' => round($socDiff / 100 * $capacity, 2),
            'energy_added_kwh'  => 0,
            'active'            => true,
        ]);

        Log::info(sprintf(
            'Schema-goal aangemaakt: schema #%d "%s" → %s, doel %d%%',
            $best->id,
            $best->label ?? $best->day_label,
            $bestNext->setTimezone('Europe/Amsterdam')->format('D d M H:i'),
            $best->target_soc,
        ));
    }

    private function updateGoalProgress(int $energySessionWh): bool
    {
        $goal = ChargeGoal::active();
        if (!$goal) return false;

        // Gebruik SoC als primaire voortgangsmaat wanneer beschikbaar.
        // EnergySession telt vanaf het begin van de laadsessie — niet vanaf het
        // aanmaken van het doel — waardoor het bij een herstart of eerder laden
        // het doel te vroeg als bereikt markeerde.
        $currentSoc = MeterReading::whereNotNull('vehicle_soc')
            ->latest('recorded_at')
            ->value('vehicle_soc');

        if ($currentSoc !== null) {
            $capacity = (float) Setting::get('battery_capacity_kwh', 60);
            $addedKwh = max(0, ($currentSoc - $goal->current_soc) / 100 * $capacity);
            $goal->update(['energy_added_kwh' => round($addedKwh, 2)]);

            // Doel bereikt op basis van SoC?
            if ($currentSoc >= $goal->target_soc) {
                $goal->update(['active' => false]);
                $this->peblar->setChargeCurrentLimit(0);
                Log::info("Laaddoel bereikt: SoC {$currentSoc}% ≥ doel {$goal->target_soc}% — laden gestopt");
                return true;
            }
            return false;
        }

        // Fallback: EnergySession — minder betrouwbaar maar werkt zonder voertuigdata
        $addedKwh = $energySessionWh / 1000;
        if ($addedKwh > $goal->energy_added_kwh) {
            $goal->update(['energy_added_kwh' => $addedKwh]);
        }
        return false;
    }

    private function getCurrentPriority(): int
    {
        // Schema-items zijn nu deadline-gebaseerde laaddoelen (geen tijdvakken meer).
        // Prioriteit wordt bepaald door het ChargePlanService-laadplan.
        // Zonder actief laaddoel valt de balancer terug op PRIORITY_NORMAL.
        return self::PRIORITY_NORMAL;
    }

    private function powerToMilliamps(int $watts, int $phases, int $maxCurrentA = 13): int
    {
        if ($watts <= 0) return 0;

        $current = ($watts / ($phases * 230)) * 1000; // mA
        $current = (int) round($current);

        if ($current < self::MIN_CHARGE_CURRENT_MA) {
            return 0; // below minimum, stop charging
        }

        // Harde cap op de ingestelde max stroom (beschermt groepenkast-automaat)
        return min($current, $maxCurrentA * 1000);
    }

    /**
     * Bepaal het effectieve zonne-laadvermogen.
     * Als het overschot lager is dan het 1-fase minimum (1.380 W), wordt het minimum
     * gebruikt zodat de lader altijd de IEC 61851-drempel haalt. Het verschil wordt
     * aangevuld vanuit het net.
     */
    private function solarChargeW(int $solarSurplusW): int
    {
        return max($solarSurplusW, self::MIN_SOLAR_CHARGE_W);
    }

    /**
     * Bepaal het gewenste aantal laadaansen op basis van beschikbare energie en laadmodus.
     *
     * Drempelwaardes (IEC 61851 minimum 6A per fase):
     *   1-fase min : 6A × 1 × 230V = 1.380 W
     *   3-fase min : 6A × 3 × 230V = 4.140 W
     *
     * Hysteresis: 500 W extra buffer om heen-en-weer schakelen te voorkomen.
     */
    private function determinePhaseCount(
        int $desiredW,
        int $solarSurplusW,
        bool $isGridCharging,
        int $maxPhases,
        int $currentPhases,
    ): int {
        // Enkelfasige installatie of geen vermogen nodig: huidig aantal / max behouden
        if ($maxPhases === 1) return 1;
        if ($desiredW <= 0) return $currentPhases;

        // Netladen altijd op max fases (goedkoopste uren / urgent)
        if ($isGridCharging) return 3;

        // Zonneladen: dynamisch op basis van surplus
        $threePhaseMinW = 6 * 3 * 230; // 4.140 W
        $switchBuffer   = 500;          // W hysteresis om flapping te voorkomen

        if ($currentPhases === 3 && $solarSurplusW < $threePhaseMinW) {
            // Surplus te laag voor 3-fase → schakel naar 1-fase
            return 1;
        }

        if ($currentPhases === 1 && $solarSurplusW >= ($threePhaseMinW + $switchBuffer)) {
            // Surplus hoog genoeg voor 3-fase → schakel terug naar 3-fase
            return 3;
        }

        // Blijf op huidig aantal fases
        return $currentPhases;
    }

    private function storeMeterReading(array $peblar, ?array $p1, ?array $solax, ?float $price): void
    {
        $meter = $peblar['meter'] ?? [];
        $evi   = $peblar['evinterface'] ?? [];

        MeterReading::create([
            'recorded_at'               => now(),
            'peblar_power_total'        => $meter['PowerTotal'] ?? null,
            'peblar_power_l1'           => $meter['PowerPhase1'] ?? null,
            'peblar_power_l2'           => $meter['PowerPhase2'] ?? null,
            'peblar_power_l3'           => $meter['PowerPhase3'] ?? null,
            'peblar_current_l1'         => $meter['CurrentPhase1'] ?? null,
            'peblar_current_l2'         => $meter['CurrentPhase2'] ?? null,
            'peblar_current_l3'         => $meter['CurrentPhase3'] ?? null,
            'peblar_voltage_l1'         => $meter['VoltagePhase1'] ?? null,
            'peblar_voltage_l2'         => $meter['VoltagePhase2'] ?? null,
            'peblar_voltage_l3'         => $meter['VoltagePhase3'] ?? null,
            'peblar_energy_session'     => $meter['EnergySession'] ?? null,
            'peblar_energy_total'       => $meter['EnergyTotal'] ?? null,
            'peblar_cp_state'           => $evi['CpState'] ?? null,
            'peblar_charge_current_limit'  => $evi['ChargeCurrentLimit'] ?? null,
            'peblar_charge_current_actual' => $evi['ChargeCurrentLimitActual'] ?? null,
            'p1_power_consumed'         => $p1['power_consumed_w'] ?? null,
            'p1_power_produced'         => $p1['power_produced_w'] ?? null,
            'p1_voltage_l1'             => $p1['voltage_l1'] ?? null,
            'p1_voltage_l2'             => $p1['voltage_l2'] ?? null,
            'p1_voltage_l3'             => $p1['voltage_l3'] ?? null,
            'solax_pv_power'            => $solax['pv_power_w'] ?? null,
            'solax_battery_soc'         => $solax['battery_soc'] ?? null,
            'solax_battery_power'       => $solax['battery_power_w'] ?? null,
            'solax_grid_power'          => $solax['grid_power_w'] ?? null,
            'price_current'             => $price,
        ]);
    }

    private function recordDecision(
        int $priority,
        int $desiredPowerW,
        int $currentMa,
        int $newMa,
        string $reason,
        ?float $price,
        ?int $solarSurplus,
        ?int $householdConsumption,
        ?int $availableGrid,
        bool $commandSent,
    ): ChargeDecision {
        $decision = ChargeDecision::create([
            'decided_at'              => now(),
            'priority'                => $priority,
            'priority_label'          => self::PRIORITY_LABELS[$priority] ?? 'Onbekend',
            'desired_power_w'         => $desiredPowerW,
            'charge_current_ma'       => $newMa,
            'previous_current_ma'     => $currentMa,
            'reason'                  => $reason,
            'price_eur'               => $price,
            'solar_surplus_w'         => $solarSurplus,
            'household_consumption_w' => $householdConsumption,
            'available_grid_w'        => $availableGrid,
            'command_sent'            => $commandSent,
        ]);

        Log::info("Load balancer: priority={$priority} current={$newMa}mA reason={$reason}");

        return $decision;
    }
}
