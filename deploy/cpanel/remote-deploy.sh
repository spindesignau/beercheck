#!/usr/bin/env bash
# Runs ON the cPanel server (invoked by .github/workflows/deploy-staging.yml).
# Reads the database password from stdin so it never appears in a command line.
#   printf '%s\n' "$DB_PASSWORD" | ssh host 'bash APP_DIR/deploy/cpanel/remote-deploy.sh APP_DIR APP_URL DB_NAME DB_USER'
set -euo pipefail

APP_DIR="$1"; APP_URL="$2"; DB_NAME="$3"; DB_USER="$4"
IFS= read -r DB_PASSWORD || true

cd "$APP_DIR"

# --- PHP 8.4 binary (cPanel EasyApache / CloudLinux alt-php / fallback) -----
PHP=""
for candidate in /usr/local/bin/ea-php84 /opt/cpanel/ea-php84/root/usr/bin/php /opt/alt/php84/usr/bin/php; do
  [ -x "$candidate" ] && PHP="$candidate" && break
done
PHP="${PHP:-php}"
echo "Using PHP: $PHP ($("$PHP" -r 'echo PHP_VERSION;'))"
"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' || { echo "PHP 8.3+ required"; exit 1; }

# --- Writable dirs (not shipped by rsync, persisted between deploys) -------
mkdir -p storage/app/public/photos storage/app/private storage/framework/{cache/data,sessions,views,testing} storage/logs bootstrap/cache
chmod -R u+rwX,g+rwX storage bootstrap/cache

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
