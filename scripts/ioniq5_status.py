#!/usr/bin/env python3
"""
Haalt de actuele status van de Hyundai Ioniq 5 op via BlueLink cloud API.
Gebruik: python3 ioniq5_status.py <email> <idp_refresh_token> [pin]

De IDP refresh token wordt ingewisseld voor een CCAPI access token dat de
hyundai_kia_connect_api bibliotheek kan gebruiken.

Output JSON naar stdout:
  { "soc": 72, "is_charging": true, "is_plugged_in": true, ... }
Bij fout:
  { "error": "beschrijving" }
"""

import sys
import json
import os
from datetime import datetime, timezone, timedelta

TOKEN_CACHE = os.path.join(os.path.dirname(__file__), "hyundai_token.json")

# CCAPI endpoint (Hyundai EU)
CCAPI_BASE    = "https://prd.eu-ccapi.hyundai.com:8080/api/v1/user/"
# CCAPI Basic Authorization — base64(client_id:client_secret)
# Set HYUNDAI_CLIENT_ID + HYUNDAI_CLIENT_SECRET in env (.env file is loaded by Laravel)
import base64 as _b64
_cid = os.environ.get("HYUNDAI_CLIENT_ID", "")
_csec = os.environ.get("HYUNDAI_CLIENT_SECRET", "")
if not _cid or not _csec:
    print(json.dumps({"error": "HYUNDAI_CLIENT_ID / HYUNDAI_CLIENT_SECRET not set in environment"}))
    sys.exit(1)
BASIC_AUTH    = "Basic " + _b64.b64encode(f"{_cid}:{_csec}".encode()).decode()
# Token geldig tot buffer (5 minuten voor expiry ververst)
TOKEN_MARGIN  = 300


def load_cache():
    if not os.path.exists(TOKEN_CACHE):
        return {}
    try:
        with open(TOKEN_CACHE) as f:
            return json.load(f)
    except Exception:
        return {}


def save_cache(data):
    try:
        with open(TOKEN_CACHE, "w") as f:
            json.dump(data, f, indent=2)
    except Exception:
        pass


def get_ccapi_access_token(idp_refresh_token):
    """Wissel IDP refresh token in voor een CCAPI access token."""
    try:
        import requests
    except ImportError:
        return None, None

    resp = requests.post(
        CCAPI_BASE + "oauth2/token",
        data=(
            "grant_type=refresh_token"
            "&redirect_uri=https%3A%2F%2Fwww.getpostman.com%2Foauth2%2Fcallback"
            f"&refresh_token={idp_refresh_token}"
        ),
        headers={
            "Authorization":  BASIC_AUTH,
            "Content-type":   "application/x-www-form-urlencoded",
            "Host":           "prd.eu-ccapi.hyundai.com:8080",
            "Connection":     "close",
            "Accept-Encoding": "gzip, deflate",
        },
        timeout=30,
    )

    if resp.status_code != 200:
        return None, None

    data = resp.json()
    access_token = data.get("access_token")
    expires_in   = int(data.get("expires_in", 3600))
    if not access_token:
        return None, None

    valid_until = (datetime.now(timezone.utc) + timedelta(seconds=expires_in - TOKEN_MARGIN)).isoformat()
    return access_token, valid_until


def get_valid_ccapi_token(idp_refresh_token):
    """Geeft een geldig CCAPI access token (uit cache of vers)."""
    cache = load_cache()
    ccapi_token  = cache.get("ccapi_access_token")
    valid_until_s = cache.get("ccapi_valid_until")

    if ccapi_token and valid_until_s:
        try:
            valid_until = datetime.fromisoformat(valid_until_s)
            if valid_until > datetime.now(timezone.utc):
                return ccapi_token, cache
        except Exception:
            pass

    # Nieuw token ophalen
    new_token, new_valid = get_ccapi_access_token(idp_refresh_token)
    if not new_token:
        return None, cache

    cache["ccapi_access_token"] = new_token
    cache["ccapi_valid_until"]  = new_valid
    save_cache(cache)
    return new_token, cache


def main():
    # Optionele --live vlag: forceert een directe statusopvraag bij de auto
    # (gebruik dit na een laadsessie voor actuele SoC; standaard: gecachede cloud-status)
    use_live = "--live" in sys.argv
    args = [a for a in sys.argv[1:] if a != "--live"]

    if len(args) < 2:
        print(json.dumps({"error": "Gebruik: ioniq5_status.py <email> <idp_refresh_token> [pin] [--live]"}))
        sys.exit(1)

    username          = args[0]
    idp_refresh_token = args[1]
    pin               = args[2] if len(args) > 2 else ""

    try:
        from hyundai_kia_connect_api import VehicleManager
        from hyundai_kia_connect_api.Token import Token
    except ImportError:
        print(json.dumps({"error": "hyundai_kia_connect_api niet geïnstalleerd"}))
        sys.exit(1)

    try:
        import requests
    except ImportError:
        print(json.dumps({"error": "requests niet geïnstalleerd"}))
        sys.exit(1)

    try:
        # Haal geldig CCAPI access token op
        ccapi_token, cache = get_valid_ccapi_token(idp_refresh_token)
        if not ccapi_token:
            print(json.dumps({"error": "Kon geen CCAPI access token ophalen. Controleer de refresh token of voer get_hyundai_token.py opnieuw uit."}))
            sys.exit(1)

        # Maak de VehicleManager aan
        manager = VehicleManager(
            region=1,    # Europe
            brand=2,     # Hyundai
            username=username,
            password=idp_refresh_token,
            pin=pin,
        )

        # Haal (gecached) device_id op of registreer een nieuw device
        device_id = cache.get("device_id")
        if not device_id:
            api = manager.api
            stamp     = api._get_stamp()
            device_id = api._get_device_id(stamp)
            cache["device_id"] = device_id
            save_cache(cache)

        # Bouw Token met CCAPI access token
        t = Token()
        t.username      = username
        t.password      = idp_refresh_token
        t.access_token  = "Bearer " + ccapi_token
        t.refresh_token = "Bearer " + ccapi_token   # Sommige calls gebruiken refresh_token veld
        t.device_id     = device_id
        t.pin           = pin
        t.valid_until   = datetime.max   # We beheren expiry zelf

        manager.token = t

        # Haal voertuigenlijst op
        vehicles = manager.api.get_vehicles(manager.token)
        for v in vehicles:
            manager.vehicles[v.id] = v

        if not manager.vehicles:
            print(json.dumps({"error": "Geen voertuigen gevonden op dit account"}))
            sys.exit(1)

        # Haal voertuigstatus op: live (--live) na einde laadsessie, anders gecachede cloud-status
        if use_live:
            manager.force_refresh_all_vehicles_states()
        else:
            manager.update_all_vehicles_with_cached_state()

        vehicle = list(manager.vehicles.values())[0]

        last_updated = None
        if vehicle.last_updated_at:
            if isinstance(vehicle.last_updated_at, datetime):
                last_updated = vehicle.last_updated_at.isoformat()
            else:
                last_updated = str(vehicle.last_updated_at)

        print(json.dumps({
            "soc":               vehicle.ev_battery_percentage,
            "is_charging":       bool(vehicle.ev_battery_is_charging),
            "is_plugged_in":     bool(vehicle.ev_battery_is_plugged_in),
            "charging_current_a": getattr(vehicle, "ev_charging_current", None),
            "range_km":          int(vehicle.ev_driving_range) if vehicle.ev_driving_range else None,
            "minutes_to_full":   vehicle.ev_estimated_current_charge_duration,
            "last_updated":      last_updated,
            "vehicle_name":      getattr(vehicle, "name", None),
        }))

    except Exception as e:
        # Verwijder alleen het CCAPI token uit de cache (refresh_token bewaren)
        try:
            cache = load_cache()
            cache.pop("ccapi_access_token", None)
            cache.pop("ccapi_valid_until", None)
            save_cache(cache)
        except Exception:
            pass
        print(json.dumps({"error": str(e)}))
        sys.exit(1)


if __name__ == "__main__":
    main()
