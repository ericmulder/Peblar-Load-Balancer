#!/usr/bin/env python3
# Voer uit met de project-venv: scripts/.venv/bin/python3 scripts/get_hyundai_token.py
"""
Eenmalig script om een Hyundai BlueLink refresh token op te halen.

Gebruik: scripts/.venv/bin/python3 scripts/get_hyundai_token.py

Vereiste: de venv is aangemaakt en selenium + chromedriver-autoinstaller zijn geïnstalleerd.
    scripts/.venv/bin/pip install selenium chromedriver-autoinstaller requests

Stappen:
  1. Er opent een Chrome-venster met de Hyundai login pagina
  2. Log in met je Hyundai My Car / BlueLink account (inclusief reCAPTCHA)
  3. Druk op ENTER in de terminal nadat je bent ingelogd
  4. Het script haalt automatisch de refresh token op en slaat die op
"""

import os
import re
import time
import sys
import json
import shutil

TOKEN_CACHE = os.path.join(os.path.dirname(__file__), "hyundai_token.json")

BASE_URL  = "https://idpconnect-eu.hyundai.com/auth/api/v2/user/oauth2/"
LOGIN_URL = (
    f"{BASE_URL}authorize?"
    "client_id=peuhyundaiidm-ctb&"
    "redirect_uri=https%3A%2F%2Fctbapi.hyundai-europe.com%2Fapi%2Fauth&"
    "nonce=&state=EN_&"
    "scope=openid+profile+email+phone&"
    "response_type=code&"
    "connector_client_id=peuhyundaiidm-ctb&"
    "connector_scope=&connector_session_key=&country=&captcha=1&"
    "ui_locales=en-US&lang=en"
)

CLIENT_ID     = os.environ.get("HYUNDAI_CLIENT_ID", "")
CLIENT_SECRET = os.environ.get("HYUNDAI_CLIENT_SECRET", "")
REDIRECT_URI  = os.environ.get("HYUNDAI_REDIRECT_URI", "https://prd.eu-ccapi.hyundai.com:8080/api/v1/user/oauth2/token")

if not CLIENT_ID or not CLIENT_SECRET:
    print("ERROR: set HYUNDAI_CLIENT_ID and HYUNDAI_CLIENT_SECRET environment variables.", file=sys.stderr)
    print("       See .env.example for details.", file=sys.stderr)
    sys.exit(1)


def install_driver():
    """Zorg dat de juiste chromedriver versie is geïnstalleerd."""
    try:
        import chromedriver_autoinstaller
        _ = chromedriver_autoinstaller.get_chrome_version()
    except Exception:
        print("❌  Google Chrome niet gevonden. Installeer Google Chrome en probeer opnieuw.")
        sys.exit(1)
    try:
        return chromedriver_autoinstaller.install()
    except Exception as e:
        print(f"❌  Kon chromedriver niet installeren: {e}")
        sys.exit(1)


def start_driver():
    """Start de Chrome WebDriver met anti-detectie opties."""
    try:
        from selenium import webdriver
        from selenium.webdriver.chrome.options import Options
        from selenium.webdriver.chrome.service import Service
        from selenium.common.exceptions import WebDriverException
        import chromedriver_autoinstaller
    except ImportError:
        print("❌  selenium of chromedriver-autoinstaller niet geïnstalleerd.")
        print("    Voer uit: scripts/.venv/bin/pip install selenium chromedriver-autoinstaller")
        sys.exit(1)

    options = Options()
    options.add_argument("--window-size=1000,800")
    options.add_argument("--disable-blink-features=AutomationControlled")
    options.add_argument(
        "user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) "
        "Chrome/125.0.0.0 Safari/537.36_CCS_APP_AOS"
    )

    driver_path = install_driver()

    try:
        service = Service(driver_path)
        return webdriver.Chrome(service=service, options=options)
    except WebDriverException:
        # Probeer opnieuw na het verwijderen van de gecachede driver
        try:
            driver_dir = os.path.dirname(driver_path) if driver_path else None
            if driver_dir and os.path.exists(driver_dir):
                shutil.rmtree(driver_dir, ignore_errors=True)
        except Exception:
            pass

        try:
            driver_path = chromedriver_autoinstaller.install()
            service = Service(driver_path)
            return webdriver.Chrome(service=service, options=options)
        except Exception as e:
            print(f"❌  Kon Chrome WebDriver niet starten: {e}")
            sys.exit(1)


def exchange_code_for_token(code: str):
    """Wissel de auth code in voor een refresh token."""
    try:
        import requests
    except ImportError:
        print("❌  requests niet geïnstalleerd. Voer uit: scripts/.venv/bin/pip install requests")
        sys.exit(1)

    response = requests.post(
        f"{BASE_URL}token",
        data={
            "grant_type":    "authorization_code",
            "code":          code,
            "redirect_uri":  REDIRECT_URI,
            "client_id":     CLIENT_ID,
            "client_secret": CLIENT_SECRET,
        },
        timeout=30,
    )

    if response.status_code == 200:
        return response.json().get("refresh_token")
    else:
        print(f"❌  Token-uitwisseling mislukt (HTTP {response.status_code}): {response.text}")
        return None


def main():
    print("=" * 60)
    print("Hyundai BlueLink — refresh token ophalen")
    print("=" * 60)
    print()
    print("Er opent nu een Chrome-venster met de Hyundai login pagina.")
    print("Stappen:")
    print("  1. Log in met je Hyundai My Car / BlueLink account")
    print("  2. Voltooi de reCAPTCHA als die verschijnt")
    print("  3. Wacht tot de pagina volledig is geladen na het inloggen")
    print("  4. Kom terug naar deze terminal en druk op ENTER")
    print()

    driver = start_driver()

    try:
        driver.get(LOGIN_URL)

        print("=" * 60)
        print("🌐  Chrome is geopend met de Hyundai login pagina")
        print("=" * 60)
        input("\nDruk op ENTER nadat je bent ingelogd... ")

        print()
        print("🔑  Auth code ophalen...")

        # Stap 2: navigeer naar het tweede authorize endpoint om de code te krijgen
        driver.get(
            f"{BASE_URL}authorize?"
            f"response_type=code&"
            f"client_id={CLIENT_ID}&"
            f"redirect_uri={REDIRECT_URI}&"
            f"lang=en&state=ccsp"
        )
        time.sleep(2)

        current_url = driver.current_url
        match = re.search(r"code=([^&]+)", current_url)

        if not match:
            print()
            print("❌  Geen auth code gevonden in de URL.")
            print(f"    Huidige URL: {current_url}")
            print()
            print("Mogelijke oorzaken:")
            print("  - Je bent niet (volledig) ingelogd")
            print("  - De sessie is verlopen — probeer opnieuw")
            driver.quit()
            sys.exit(1)

        code = match.group(1)
        print(f"✅  Auth code ontvangen")

        print("🔄  Refresh token ophalen via token endpoint...")
        refresh_token = exchange_code_for_token(code)

        if not refresh_token:
            driver.quit()
            sys.exit(1)

        print()
        print("=" * 60)
        print("✅  Refresh token succesvol opgehaald!")
        print("=" * 60)
        print()
        print(f"Refresh token:  {refresh_token}")
        print()

        # Sla op als JSON
        data = {"refresh_token": refresh_token}
        with open(TOKEN_CACHE, "w") as f:
            json.dump(data, f, indent=2)

        print(f"💾  Opgeslagen in: {TOKEN_CACHE}")
        print()
        print("👉  Kopieer de refresh token naar:")
        print("    Peblar app → Instellingen → Hyundai BlueLink → Refresh Token")
        print()

    except KeyboardInterrupt:
        print("\n\nAfgebroken.")
    finally:
        try:
            driver.quit()
        except Exception:
            pass


if __name__ == "__main__":
    main()
