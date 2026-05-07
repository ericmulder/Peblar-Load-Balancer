# Contributing

Bedankt voor interesse in dit project. Forks en PRs zijn van harte welkom.

## Soorten bijdragen

- **Bug fixes** — open issue of stuur direct PR.
- **Nieuwe integratie** (auto, omvormer, prijsbron, smart meter) — zie
  hieronder. Liefst eerst issue openen om scope af te stemmen.
- **UI / dashboard verbeteringen** — graag screenshot meesturen.

## Nieuwe integratie toevoegen

Het project is opgezet rond losse services per leverancier. Voorbeeld: nieuwe
auto-integratie (bv. Tesla) toevoegen:

1. Maak `app/Services/TeslaService.php` met dezelfde public methods als
   `HyundaiService` (`getStatus()`, `isCharging()`, `isPluggedIn()`, etc.).
   Zie [`AGENT.md`](AGENT.md) voor de volledige contract-beschrijving.
2. Voeg config-keys toe aan `config/peblar.php` en `.env.example`.
3. Voeg settings-rijen toe in een nieuwe migratie (niet de bestaande aanpassen).
4. Bind je service in `app/Providers/AppServiceProvider.php` op basis van een
   `vehicle_driver` setting.
5. Schrijf minimaal een unit-test in `tests/Unit/`.
6. Update README onder "Optionele integraties".

Hetzelfde patroon geldt voor inverters (`SolaxService`-stijl), prijsbronnen
(`ZonneplanService`-stijl) en smart meters (`P1MeterService`-stijl).

## Code style

- PSR-12 voor PHP (`./vendor/bin/pint` als formatter).
- Conventional Commits voor commit messages.
- Strict types waar redelijk.
- Geen secrets in commits — `.env.example` bevat alleen placeholders.

## Branching

Korte vuistregel:

- Fork → branch vanuit `main` → PR naar `main`.
- Voor groter werk: `feature/<naam>` of `integration/<leverancier>`.
- Werk niet rechtstreeks in `main` van je fork als je upstream wilt blijven volgen.

## Tests

```bash
php artisan test
```

PR's worden alleen gemerged als de testsuite groen is.

## Veiligheid

Vond je een security-issue? Stuur het **niet** als publieke PR. Zie
[`SECURITY.md`](SECURITY.md).
