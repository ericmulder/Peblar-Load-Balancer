# Peblar Load Balancer — Agent Guide

Dit project is gebouwd met Claude Code (Anthropic). Dit document beschrijft de architectuur,
conventies en regels voor verdere ontwikkeling door AI-agents.

---

## Projectdoel

Dynamisch laden van een Ioniq 5 op een Peblar laadpaal, gestuurd door:
- **P1-meter** (Slimmelezer) — live huishoudverbruik
- **Solax omvormer** — zonne-opbrengst en thuisbatterij
- **Zonneplan API** — day-ahead stroomprijzen
- **Hyundai BlueLink** — SoC en laadstatus van de auto

Het systeem kiest elke minuut het optimale laadvermogen: maximaal zonne-overschot benutten,
goedkoopste uren plannen, en de groepenkast nooit overbelasten.

---

## Stack

| Laag | Technologie |
|------|-------------|
| Backend | Laravel 11 (PHP 8.2) |
| Frontend | Blade + Alpine.js + Tailwind CSS CDN |
| Database | SQLite |
| Scheduling | Laravel Scheduler — `schedule:work` (docker/supervisord) of `schedule:run` via crontab (non-docker) |
| Scripting | Python 3 (scripts/) voor Hyundai token |

---

## Mapstructuur

```
app/
  Console/Commands/        — Artisan-commands (scheduler-taken)
    RunLoadBalancer.php    — Hoofdcyclus: elke minuut via cron
    FetchVehicleStatus.php — Hyundai BlueLink polling
    FetchZonneplanPrices.php — Day-ahead prijzen ophalen

  Http/Controllers/        — Thin controllers, logica in Services
    ApiController.php      — JSON-endpoints voor dashboard (polling)
    DashboardController.php
    StrategyController.php — Laadplan + doel
    ScheduleController.php — Terugkerende laaddoelen
    ChargeGoalController.php
    SettingsController.php

  Models/
    MeterReading.php       — Elke minuut opgeslagen sensor-snapshot
    ChargeDecision.php     — Elke minuut opgeslagen balancer-beslissing
    ChargeGoal.php         — Actief laaddoel (depart_at, target_soc)
    ChargeSchedule.php     — Terugkerend schema (dag + tijd + doel-SoC)
    PriceForecast.php      — Uurprijzen van Zonneplan
    Setting.php            — Key-value instellingen (groepen: balancer/peblar/solax/p1/hyundai)

  Services/
    LoadBalancerService.php  — Hoofdlogica: data verzamelen → beslissen → sturen
    ChargePlanService.php    — Prijsoptimalisatie: goedkoopste uren selecteren
    PeblarService.php        — Peblar REST API wrapper
    P1MeterService.php       — Slimmelezer SSE/HTTP wrapper
    SolaxService.php         — Solax lokale API wrapper
    ZonneplanService.php     — Zonneplan JWT + prijzen
    HyundaiService.php       — BlueLink data via Python-script

resources/views/
  layouts/app.blade.php    — Navigatie, Tailwind CDN, Alpine.js
  dashboard.blade.php      — Live overzicht (Alpine polling elke 10s)
  strategy.blade.php       — Laadplan en uitleg
  schedule.blade.php       — Terugkerende laaddoelen (schema)
  settings.blade.php       — Alle instellingen per groep
  history.blade.php        — Grafieken

routes/
  web.php                  — Alle web + API routes
  console.php              — Laravel Scheduler definities

scripts/
  get_hyundai_token.py     — Selenium-based OAuth (Hyundai Token Solution)
  ioniq5_status.py         — CCAPI token exchange + voertuigstatus ophalen
  hyundai_token.json       — Gecachte refresh_token + CCAPI device_id (niet in git)

database/migrations/       — Elke schema-wijziging als migratie
```

---

## Kernlogica: LoadBalancerService::run()

Elke minuut doorloopt de balancer deze stappen:

1. **Data ophalen** — Peblar, P1, Solax, Zonneplan prijs
2. **Plug-in detectie** — als CpState wisselt van State A → iets anders, forceer vehicle fetch
3. **MeterReading opslaan** — snapshot van alle sensorwaarden
4. **GoalProgress bijwerken** — kWh toegevoegd aan actief laaddoel
5. **SyncScheduleGoal** — check of een schema-item zijn `plan_ahead_hours`-venster ingaat; zo ja, maak automatisch een `ChargeGoal` aan
6. **ChargePlanService** — bepaal of dit uur een laaduur is (prijsoptimalisatie)
7. **Prioriteit bepalen** — STOP / LOW / NORMAL / HIGH / URGENT
8. **Decide** — bereken gewenst laadvermogen in Watt
9. **powerToMilliamps** — converteer naar mA, pas harde cap toe op `max_charge_current_a`
10. **Hysteresis** — stuur alleen een update als verschil > 500mA
11. **ChargeDecision opslaan** — log beslissing + reden

### Vermogensbescherming (twee lagen)

```
Laag 1 — per fase:   min(berekend, max_charge_current_a × 1000) mA
Laag 2 — totaal net: availableGridW = gridCapacityW − householdW − gridBufferW
```

---

## Instellingen (settings-tabel, groepen)

| Key | Default | Omschrijving |
|-----|---------|--------------|
| `max_charge_current_a` | 13 | Hard max per fase (A) — pas aan bij zwaardere automaat |
| `grid_capacity_w` | 17250 | Hoofdzekering (25A × 3 × 230V) |
| `grid_buffer_w` | 500 | Veiligheidsmarje boven hoofdzekering |
| `phase_count` | 3 | Aantal fases |
| `price_threshold_high` | 0.15 | Altijd laden onder dit tarief (€/kWh) |
| `price_threshold_normal` | 0.25 | Normaal laden onder dit tarief |
| `solar_min_surplus_w` | 1500 | Minimaal PV-overschot voor zonne-laden |
| `hysteresis_ma` | 500 | Minimale wijziging voor nieuwe Peblar-opdracht |
| `battery_capacity_kwh` | 60 | EV batterijcapaciteit |
| `default_target_soc` | 90 | Standaard laaddoel (%) |

---

## Hyundai BlueLink

- **Token ophalen**: `scripts/get_hyundai_token.py` — Selenium + handmatig inloggen → `hyundai_token.json`
- **Status ophalen**: `scripts/ioniq5_status.py` — IDP refresh_token → CCAPI JWT → voertuigstatus
- **Polling**: alleen als `vehicle_plugged_in = true` (12V accu sparen), of bij plug-in event, of via force-refresh knop
- **Opslag**: vehicle-kolommen op `MeterReading` (los van balancer-readings)

---

## Database conventies

- Elke schema-wijziging = **nieuwe migratie**, nooit de bestaande aanpassen
- `MeterReading` — sensordata per minuut; vehicle-kolommen nullable (apart gevuld)
- `ChargeDecision` — balancer-uitkomst per minuut
- `Setting` — key/value met `group` voor UI-groepering; nieuwe settings altijd via migratie toevoegen met `insertOrIgnore`

---

## Frontend conventies

- **Alpine.js** — reactieve state in `dashboard.blade.php`; geen frameworks
- **Tailwind CDN** — `<style type="text/tailwindcss">` voor `@apply`
- **Chart.js v4** — gebruik altijd `Chart.getChart('id')?.destroy()` vóór nieuwe instantie
- Kaarten hebben class `.card` → `@apply bg-white rounded-2xl shadow-md border border-gray-200 p-5`

---

## Commit-conventies

Dit project gebruikt **Conventional Commits**. Elke PR of verzoek krijgt één of meer commits:

```
<type>(<scope>): <omschrijving>

[optioneel body]
```

### Types

| Type | Gebruik |
|------|---------|
| `feat` | Nieuwe feature of functionaliteit |
| `fix` | Bugfix |
| `refactor` | Code-verbetering zonder gedragswijziging |
| `chore` | Migraties, config, dependencies |
| `docs` | Alleen documentatie |
| `style` | Alleen UI/CSS wijzigingen |
| `perf` | Performance-verbetering |

### Scopes (voorbeelden)

`balancer`, `vehicle`, `schedule`, `strategy`, `dashboard`, `settings`, `api`, `migrations`, `scripts`

### Voorbeelden

```
feat(balancer): add max_charge_current_a setting with hard amp cap
fix(vehicle): include range_km in StrategyController vehicleData
feat(schedule): auto-create ChargeGoal when deadline within plan_ahead_hours
chore(migrations): add max_charge_current_a setting, fix grid_capacity_w default
docs(agent): add AGENT.md with architecture and commit conventions
```

### Regel

> **Eén commit per verzoek of logische feature.** Meerdere bestanden mogen in één commit
> als ze samen één feature vormen. Gebruik de body voor uitleg als de omschrijving niet volstaat.

---

## Lokale ontwikkeling

```bash
# Laravel development server
php artisan serve

# Scheduler draaien (development)
php artisan schedule:work

# Handmatige balancer-cyclus
php artisan peblar:balance

# Voertuig forceren
php artisan peblar:fetch-vehicle --force

# Migraties
php artisan migrate
```

### Scheduler

De minuut-cyclus van de load balancer wordt aangestuurd door Laravel's
scheduler. Hoe je die activeert hangt af van de deployment-modus:

**Docker (standaard via `docker compose`)** — niets te doen. Supervisord in
de container draait permanent `php artisan schedule:work`. Zie
`docker/supervisord.conf`.

**Non-docker self-host** — kies één van:

```cron
# Optie 1: crontab -e
* * * * * cd /path/to/peblar && php artisan schedule:run >> /dev/null 2>&1
```

```bash
# Optie 2: permanente worker via systemd of supervisord
php artisan schedule:work
```

Zonder een van beide blijft de balancer stilliggen ook als alle instellingen
correct zijn. Het settings-scherm detecteert automatisch of de instance in
Docker draait en toont de juiste instructie.

---

## Adapter pattern voor forks

Het project is bewust opgesplitst in losse services per externe leverancier
zodat forks eenvoudig integraties kunnen toevoegen of vervangen.

### Vehicle adapters (`app/Services/*Service.php` voor auto)

Een vehicle-adapter implementeert deze public methods:

| Methode               | Return                  | Beschrijving                              |
|-----------------------|-------------------------|-------------------------------------------|
| `getStatus(bool $live = false)` | `?array`     | `['soc' => int, 'is_charging' => bool, 'is_plugged_in' => bool, 'range_km' => int, ...]` of `null` als adapter is uitgeschakeld |
| `isEnabled()`         | `bool`                  | True als configuratie compleet is         |

`HyundaiService` is de referentie-implementatie. Een nieuwe adapter (bv.
`TeslaService`) levert dezelfde shape en kan in `AppServiceProvider` gebonden
worden op basis van een `vehicle_driver` setting.

### Inverter adapters (Solax-stijl)

| Methode               | Return  |
|-----------------------|---------|
| `getCurrentProduction()` | `int` watt |
| `getBatteryState()`     | `array{soc:int, power:int}` of `null` |

### Price adapters (Zonneplan-stijl)

| Methode               | Return  |
|-----------------------|---------|
| `getHourlyPrices(Carbon $from, Carbon $to)` | `array<int, float>` keyed by hour-of-day |

### Smart meter adapters (P1-stijl)

| Methode               | Return  |
|-----------------------|---------|
| `getCurrentNetUsage()` | `int` watt (positief = import, negatief = export) |

`LoadBalancerService` is de enige consumer en injecteert deze services via
de container. Een fork hoeft nooit `LoadBalancerService` zelf aan te passen
om een andere auto/omvormer/leverancier te ondersteunen — alleen de service
schrijven en de binding wisselen.
