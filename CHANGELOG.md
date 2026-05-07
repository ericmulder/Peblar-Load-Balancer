# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.0] - 2026-05-07

### Added
- Geschiedenis: knop voor `30d` (720 uur) toegevoegd aan periode-selector.
- Geschiedenis: custom datumbereik via from/to date-pickers naast de
  uren-knoppen — submit als GET-form, herrendert pagina + grafieken +
  widgets met dat bereik.
- `/api/history` accepteert nu `?hours=N` als alternatief voor
  `from`/`to` (uren-precisie i.p.v. dag-range).

### Changed
- Geschiedenis: dubbele datum-selector verwijderd. Widgets (herkomst,
  netkosten, totaal kWh) en grafieken eronder gebruiken nu hetzelfde
  periode-bereik als de top-selector.
- Settings-scherm: scheduler-uitleg detecteert automatisch of de
  instance in Docker draait. Toont groen 'al actief' blok in container,
  gele 'self-host' blok met crontab-snippet en systemd-fallback buiten
  container.
- Services (`PeblarService`, `SolaxService`, `P1MeterService`):
  short-circuit als IP leeg, voorkomt `MalformedUriException` bij fresh
  installs zonder hardware-config.
- Migraties `2026_04_09_145428_repurpose_charge_schedules_as_recurring_goals`
  en `2026_04_09_145507_add_missing_columns_to_charge_schedules`
  geconsolideerd in de create-migratie. Voorkomt duplicate-column errors
  bij fresh install.
- `docker-compose.yml`: mount `.env` als volume i.p.v. `env_file:` om
  APP_KEY override-bug te voorkomen waarbij een lege `APP_KEY` uit het
  env-file de gegenereerde sleutel uit het entrypoint blokkeerde.

### Open source
- Repo voorbereid voor publieke release (zie README/LICENSE/SECURITY.md
  in `peblar-public` workflow). Hyundai client credentials uitgehaald
  uit code en verplaatst naar env vars.

## [1.3.6] - 2026-04-28

### Fixed
- Geschiedenis-stats: zon%/net% verdeling gebruikte `p1_power_produced`
  (teruglevering naar net) als zonne-indicator. Bij zon-laden gaat alle
  PV-opbrengst direct naar de auto → P1 produced = 0 → stats telden alles
  als grid (~50% net terwijl balancer 100% op zon draaide). Vervangen door
  dezelfde formule als de balancer: `solarShare = peblar_power - (p1_consumed
  - p1_produced)`. Werkt onafhankelijk van het reading-interval.

## [1.3.5] - 2026-04-25

### Fixed
- "Stoppen" knop in "Nu laden tot" widget stuurt nu ook direct een stop-commando
  naar de Peblar — voorheen werd alleen de force-charge vlag uitgezet en bleef
  de auto doorladen tot de balancer iets anders besloot

## [1.3.4] - 2026-04-25

### Fixed
- "Nu laden tot" (force-charge): forceer ook fases naar max bij start, niet alleen
  laadstroom — voorheen bleef de auto op 1-fase hangen als die uit zonne-overschot
  modus kwam
- "Handmatig instellen (30 min)": de `phases` parameter werd gevalideerd maar nooit
  toegepast; nu wordt `setPhaseCount()` aangeroepen volgens hetzelfde patroon als
  in de normale balancer-flow (stop laden bij opschalen 1→3, dan fases, dan stroom)

## [1.3.3] - 2026-04-23

### Fixed
- Solar surplus formula corrected to `max(0, peblarPower - netGridW)` where
  `netGridW = p1Consumed - p1Produced` — accounts for both grid import and
  export scenarios, fixes consistent 2500 W over-reporting

## [1.3.2] - 2026-04-23

### Fixed
- Dashboard solar surplus widget now reads `decision.solar_surplus_w` instead of re-computing
  from mixed live/stale data — prevents inflated values (e.g. 11 kW) when P1 data lags behind
  live Peblar readings

## [1.3.1] - 2026-04-23

### Fixed
- Strategy page: replace hardcoded `15 ct/kWh` threshold with dynamic value from `price_threshold` setting

## [1.3.0] - 2026-04-23

### Added
- Smart cheap-hour selection: when price ≤ threshold, `ChargePlanService::cheapHourDecision()` looks
  8 hours ahead, calculates required charging hours from SoC gap, and only charges if the current
  hour is among the cheapest N hours in that window — avoiding unnecessary charging before cheaper
  hours arrive

## [1.2.0] - 2026-04-23

### Added
- Version verification in deploy pipeline: git tag baked into Docker image via `APP_VERSION` build arg
- `GET /api/version` endpoint returns `{"version":"vX.Y.Z"}` from container environment
- `deploy.sh` post-deploy check: verifies running container reports the expected git tag version

## [1.1.0] - 2026-04-23

### Added
- History widgets on Geschiedenis page: zon% vs net% visual bar, net cost breakdown, total kWh
- Period filters: vandaag, 7d, 30d, 365d and custom date range picker
- `GET /api/history?from=&to=` endpoint aggregating solar_kwh, grid_kwh, solar_pct, total_cost_eur
  from `meter_readings` using delta peblar_energy_total and p1_power_produced for solar/grid split

## [1.0.0] - 2026-04-22

### Added
- Core data models, migrations, and sensor services (P1, Peblar, Solax)
- Priority-based load balancer with solar surplus, price threshold, and planning integration
- Hyundai BlueLink vehicle integration with plug-in detection and live SoC polling
- Recurring deadline charge goals replacing static schedules
- `max_charge_current_a` setting with two-layer grid protection
- Dynamic 1-phase / 3-phase switching based on solar surplus (IEC 61851 minimum enforcement)
- Manual override slider with phase-aware power control on dashboard
- Force-charge mode bypassing balancer logic
- Solar forecast service with Solax PV data and settings UI
- Filterable decisions history table with hide-no-car toggle
- Solar surplus indicator on dashboard (thuisverbruik card)
- Hyundai live polling on charge start (force-refresh vehicle data)
- Docker deployment with Dockerfile and docker-compose

### Fixed
- Solar surplus gross-up: add active charge power (`power_w`) back to P1 export to prevent
  pendulum when the car consumes all solar generation
- Use `Force1Phase` API field as fallback for phase detection instead of `evinterface.Phases`
  (which resets to 3 on every stop), eliminating unnecessary 3→1 phase switches on restart
- P1 export used as solar surplus signal for phase-imbalanced installations
- Always charge during planner-selected hours regardless of price threshold
- Sync `balancer_enabled` and `force_charge` state correctly via dashboard poll
- `updateOrInsert` for Setting model so new keys are actually persisted
- Override lock, goal progress tracking, price upsert, and phase API correctness
- Cap manual override slider and API validation to `max_charge_current_a`
- Grid charging in cheapest planner hours now runs at full power instead of spread evenly
- IEC 61851 minimum (1380 W / 6 A) enforced in override slider and surplus setting

### Changed
- Price threshold unified to single `price_threshold` setting; removed separate
  `price_threshold_high` / `price_threshold_normal` split
- `price_threshold` now overrules all priorities including plan-skip decisions

[1.0.0]: https://github.com/yourorg/peblar/releases/tag/v1.0.0
