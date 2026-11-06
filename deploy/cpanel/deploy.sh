#!/usr/bin/env bash
# Run from cPanel Terminal / SSH inside ~/beercheck after uploading or `git pull`.
# Usage: PHP=/usr/local/bin/ea-php84 ./deploy/cpanel/deploy.sh
set -euo pipefail

PHP="${PHP:-/usr/local/bin/ea-php84}"
COMPOSER="${COMPOSER:-$HOME/bin/composer}"

cd "$(dirname "$0")/../.."

"$PHP" artisan down --retry=30 || true

"$PHP" "$COMPOSER" install --no-dev --prefer-dist --optimize-autoloader --no-interaction
"$PHP" artisan migrate --force
"$PHP" artisan db:seed --class=BeerSeeder --force

"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache
"$PHP" artisan event:cache

# Uploads: create public/storage -> storage/app/public unless PUBLIC_DISK_ROOT is used.
if ! grep -q '^PUBLIC_DISK_ROOT=' .env; then
  "$PHP" artisan storage:link || echo "storage:link failed - see docs/DEPLOYMENT_CPANEL.md section 5"
fi

# Let the cron queue worker pick up new code.
"$PHP" artisan queue:restart

"$PHP" artisan up
echo "Deployed."
