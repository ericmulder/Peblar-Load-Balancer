# Security Policy

## Reporting a vulnerability

Vond je een security-issue? Maak **geen** publiek GitHub issue.

Stuur in plaats daarvan een privé melding via [GitHub Security Advisories](../../security/advisories/new),
of mail naar het adres in de `LICENSE` van de repo-owner.

Vermeld:
- Beschrijving van het probleem
- Stappen om te reproduceren
- Mogelijke impact
- Eventuele suggestie voor fix

Reactie zo snel mogelijk.

## Scope

Dit project communiceert met lokale apparaten (Peblar, P1, Solax) en externe
APIs (Hyundai, Zonneplan). Issues binnen deze scope zijn welkom:

- Authenticatie/autorisatie problemen in de webinterface
- Token-leakage in logs of HTTP-responses
- SQL-injectie, XSS, CSRF
- Onveilige defaults

Buiten scope:
- Self-hosted deployment zonder reverse proxy / TLS — gebruikers zijn zelf
  verantwoordelijk voor netwerk-segmentatie en TLS-termination.
- DoS via lokale netwerktoegang.

## Geheimen in dit project

Geen geheimen in deze repo. `.env.example` bevat alleen lege placeholders.
Hyundai BlueLink `CLIENT_ID` en `CLIENT_SECRET` zijn reverse-engineered
constanten uit de publieke BlueLink mobile app — vindbaar via de community
library [hyundai_kia_connect_api](https://github.com/Hyundai-Kia-Connect/hyundai_kia_connect_api).
Zelf invullen in `.env` als je de Hyundai-integratie wilt gebruiken.
