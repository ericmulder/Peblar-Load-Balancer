#!/usr/bin/env bash
set -e

cd /var/www/html

if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        echo "==> .env not found — copying .env.example. Edit .env and restart!"
        cp .env.example .env
    else
        echo "ERROR: .env not mounted at /var/www/html/.env"; exit 1;
    fi
fi

# Generate APP_KEY if missing
if ! grep -qE '^APP_KEY=.+' .env 2>/dev/null; then
    php artisan key:generate --force --no-interaction
fi

# Ensure storage directory structure (volume mount starts empty)
mkdir -p storage/{app/public,framework/{cache/data,sessions,testing,views},logs}
chown -R www-data:www-data storage database bootstrap/cache
chmod -R 775 storage database bootstrap/cache

# Resolve SQLite path (DB_DATABASE may point outside the app dir, e.g. /data/database.sqlite)
DB_PATH="${DB_DATABASE:-database/database.sqlite}"
DB_DIR="$(dirname "$DB_PATH")"
mkdir -p "$DB_DIR"
[ -f "$DB_PATH" ] || touch "$DB_PATH"
chown -R www-data:www-data "$DB_DIR"
chmod 775 "$DB_DIR"
chmod 664 "$DB_PATH"

# Run migrations
php artisan migrate --force --no-interaction

# Cache config for performance
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

# Clear stale schedule mutex locks left over from previous container runs.
# withoutOverlapping() locks are not released on SIGTERM, causing schedule:work
# to silently skip every scheduled command after a container restart.
php artisan schedule:clear-cache --no-interaction

exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
