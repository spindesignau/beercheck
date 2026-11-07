#!/usr/bin/env bash
# Runs ON the cPanel server (invoked by .github/workflows/deploy-staging.yml).
# Reads the database password from stdin so it never appears in a command line.
#   printf '%s\n' "$DB_PASSWORD" | ssh host 'bash APP_DIR/deploy/cpanel/remote-deploy.sh APP_DIR APP_URL DB_NAME DB_USER'
set -euo pipefail

APP_DIR="$1"; APP_URL="$2"; DB_NAME="$3"; DB_USER="$4"; DEMO="${5:-false}"
IFS= read -r DB_PASSWORD || true

cd "$APP_DIR"

# --- PHP 8.4 binary (cPanel EasyApache / CloudLinux alt-php / fallback) -----
source "$APP_DIR/deploy/cpanel/php.sh"
echo "Using PHP: $PHP ($("$PHP" -r 'echo PHP_VERSION;'))"
"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' || { echo "PHP 8.3+ required"; exit 1; }

# --- Writable dirs (not shipped by rsync, persisted between deploys) -------
mkdir -p storage/app/public/photos storage/app/private storage/framework/{cache/data,sessions,views,testing} storage/logs bootstrap/cache
# PHP runs as the cPanel user (suPHP/LSAPI/FPM): owner-writable is enough, and
# group/world-writable dirs make suPHP refuse to run scripts. The web server only
# needs to traverse the path down to public/ and read files there.
chmod 755 "$APP_DIR" "$APP_DIR/public"
find "$APP_DIR/public" -type d -exec chmod 755 {} + 2>/dev/null || true
find "$APP_DIR/public" -type f -exec chmod 644 {} + 2>/dev/null || true
chmod -R u+rwX,go-w storage bootstrap/cache

# --- .env (created once; managed keys re-applied every deploy) -------------
FIRST_DEPLOY=0
if [ ! -f .env ]; then
  cp .env.example .env
  FIRST_DEPLOY=1
fi

"$PHP" deploy/cpanel/set-env.php .env <<ENV
APP_NAME=Beercheck
APP_ENV=production
APP_DEBUG=false
APP_URL=${APP_URL}
LOG_STACK=daily
LOG_LEVEL=debug
DB_CONNECTION=mariadb
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=2000
CACHE_STORE=database
FILESYSTEM_DISK=local
IMAGE_DRIVER=gd
BEERCHECK_DEMO=${DEMO}
ENV

if ! grep -q '^APP_KEY=.\+' .env; then
  "$PHP" artisan key:generate --force --no-interaction
fi

# --- Framework ----------------------------------------------------------------
"$PHP" artisan package:discover --ansi
"$PHP" artisan down --retry=15 || true
trap '"$PHP" artisan up || true' EXIT

"$PHP" artisan migrate --force --no-interaction
"$PHP" artisan db:seed --class=BeerSeeder --force --no-interaction

# Public storage link (falls back to ln -s when PHP's symlink() is disabled).
if [ ! -e public/storage ]; then
  "$PHP" artisan storage:link --no-interaction || ln -s "$APP_DIR/storage/app/public" public/storage
fi

# Staging only: fictional demo venues/prices (runs once; no-op when already seeded or demo is off).
if [ "$DEMO" = "true" ]; then
  "$PHP" artisan app:demo-content --force --no-interaction
fi

"$PHP" artisan optimize:clear >/dev/null
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache
"$PHP" artisan event:cache
"$PHP" artisan queue:restart

# --- Scheduler cron (idempotent) ----------------------------------------------
CRON_LINE="* * * * * cd $APP_DIR && $PHP artisan schedule:run >> /dev/null 2>&1 # beercheck-scheduler"
if command -v crontab >/dev/null 2>&1; then
  ( crontab -l 2>/dev/null | grep -v '# beercheck-scheduler' ; echo "$CRON_LINE" ) | crontab - \
    && echo "Scheduler cron installed." \
    || echo "WARNING: could not install cron - add it in cPanel > Cron Jobs: $CRON_LINE"
else
  echo "WARNING: crontab unavailable - add it in cPanel > Cron Jobs: $CRON_LINE"
fi

[ "$FIRST_DEPLOY" = 1 ] && echo "First deploy: .env created and APP_KEY generated."
echo "Deploy finished at $(date -u)."
