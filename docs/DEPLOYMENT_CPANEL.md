# Deploying Beercheck to cPanel (Apache or LiteSpeed)

This guide targets a standard cPanel account with SSH or Terminal access, MultiPHP and MariaDB.
Replace `USER` with your cPanel username and `beercheck.com.au` with your domain.

## 0. Requirements checklist

| Item | Where in cPanel | Value |
|---|---|---|
| PHP 8.4 (8.3 minimum) | *MultiPHP Manager* | `ea-php84` (or `alt-php84` on CloudLinux) |
| PHP extensions | *Select PHP Version → Extensions* (CloudLinux) or ask your host | `pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, fileinfo, bcmath, intl, zip, gd` (`imagick` optional), `curl` |
| PHP ini | *MultiPHP INI Editor* | `memory_limit = 256M`, `upload_max_filesize = 12M`, `post_max_size = 14M`, `max_execution_time = 60`, `allow_url_fopen = On` |
| MariaDB 10.11+ | *Server Information* | Must support `ST_Distance_Sphere` (MariaDB ≥ 10.2.38) and window functions |
| SSH / Terminal | *Terminal* | Needed for composer and artisan |
| Composer | `~/bin/composer` | `curl -sS https://getcomposer.org/installer \| php -- --install-dir=$HOME/bin --filename=composer` |

**Node is not needed on the server.** Run `npm ci && npm run build` locally or in CI, then upload
`public/build/`.

## 1. Directory layout

The app must live **outside** the web root so `.env`, `vendor/` and `storage/` are never web-reachable.

```
/home/USER/
├── beercheck/            ← whole Laravel project (git clone or upload)
│   ├── app/ … vendor/ storage/ .env
│   └── public/           ← the only directory that may be web-served
└── public_html/          ← cPanel default document root
```

## 2. Document root (choose one)

**Option A: point the domain at `public/` (recommended).**
cPanel → *Domains* → *Manage* for the domain → set **Document Root** to `beercheck/public`.
Addon domains and subdomains always allow this. Recent cPanel versions also allow it for the primary
domain. Nothing else is needed.

**Option B: symlink `public_html` to `public/`.**
```bash
cd ~
mv public_html public_html.bak        # keep cPanel's original folder
ln -s ~/beercheck/public public_html
```
This needs Apache `FollowSymLinks` or `SymLinksIfOwnerMatch`, which cPanel enables by default. On
LiteSpeed it works the same way.

**Option C: copy `public/` into `public_html` (no symlinks allowed).**
```bash
cp -r ~/beercheck/public/. ~/public_html/
cp ~/beercheck/deploy/cpanel/public_html-index.php ~/public_html/index.php
```
The replacement `index.php` loads `~/beercheck/vendor` and `bootstrap`, and calls `usePublicPath()` so
Vite assets resolve from `public_html/build`. After each frontend build, copy `public/build` into
`public_html/build` again.

### `.htaccess`
`public/.htaccess` ships with the app. In Option C it is copied into `public_html`. It handles:
- front-controller rewriting (`index.php`) and passing the `Authorization` header through
- forcing HTTPS, including behind LiteSpeed or Cloudflare (`X-Forwarded-Proto`)
- denying dotfiles, security headers, and long cache lifetimes for fingerprinted assets and photos
- `php_value` upload limits (only honoured under mod_php; use the *MultiPHP INI Editor* for
  LSAPI/PHP-FPM)

If you use Option A or B and cPanel recreated a `public_html/.htaccess` containing an `AddHandler` or
`SetHandler` line for PHP, keep that line at the top of `public/.htaccess`. It selects the PHP version.

## 3. MariaDB

1. cPanel → *Manage My Databases*
   - Create database `USER_beercheck`.
   - Create user `USER_bcapp` with a long random password.
   - Add the user to the database with **ALL PRIVILEGES**. Migrations need `CREATE`, `ALTER` and
     `INDEX`. `TRIGGER` is not required.
2. Collation: cPanel creates databases as `utf8mb4_unicode_ci` or `utf8mb4_general_ci`, and both are
   fine. In phpMyAdmin, check the server is ≥ 10.11 by running `SELECT VERSION();`.
3. Check that spatial functions are available:
   ```sql
   SELECT ST_Distance_Sphere(POINT(144.9631,-37.8136), POINT(151.2093,-33.8688));  -- ≈ 713 km in metres
   ```
4. `.env`:
   ```dotenv
   DB_CONNECTION=mariadb
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=USER_beercheck
   DB_USERNAME=USER_bcapp
   DB_PASSWORD=********
   ```
   Use the `mariadb` driver, not `mysql`. The app checks the driver name to pick the spatial SQL. Both
   drivers are treated as spatial-capable.

## 4. First install

```bash
cd ~
git clone https://github.com/spindesignau/beercheck.git    # or upload a release zip
cd beercheck
cp .env.example .env
nano .env    # APP_ENV=production, APP_DEBUG=false, APP_URL=https://beercheck.com.au, DB_*, MAIL_*, SYNC_*_URL, GEOCODING_USER_AGENT
/usr/local/bin/ea-php84 ~/bin/composer install --no-dev --optimize-autoloader
/usr/local/bin/ea-php84 artisan key:generate
/usr/local/bin/ea-php84 artisan migrate --force
/usr/local/bin/ea-php84 artisan db:seed --class=BeerSeeder --force
/usr/local/bin/ea-php84 artisan storage:link      # see §5
# upload public/build/ from your machine (npm run build)
PHP=/usr/local/bin/ea-php84 ./deploy/cpanel/deploy.sh
```

Create the first admin user:
```bash
/usr/local/bin/ea-php84 artisan tinker --execute='
$u = App\Models\User::where("email","you@example.com")->firstOrFail();
$u->forceFill(["role" => App\Enums\UserRole::Admin])->save();'
```
Register through the website first, then run this command.

Permissions: `chmod -R ug+rwX storage bootstrap/cache`. On suPHP, LSAPI or FPM, PHP runs as your cPanel
user, so no `777` is needed.

Recommended production `.env` values:
```dotenv
APP_ENV=production
APP_DEBUG=false
LOG_STACK=daily
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=2000
MAIL_MAILER=smtp           # cPanel email account: MAIL_HOST=mail.beercheck.com.au, MAIL_PORT=465, MAIL_SCHEME=smtps
```

## 5. Public storage for uploads (`storage:link`)

Photos are written to the `public` disk. The disk root is `storage/app/public` and its URL is
`APP_URL/storage`.

| Situation | What to do |
|---|---|
| Option A or B, `symlink()` allowed | `php artisan storage:link` creates `public/storage → storage/app/public`. |
| `symlink()` disabled in PHP (common on shared hosting) | Create the link from the shell instead: `ln -s ~/beercheck/storage/app/public ~/beercheck/public/storage` |
| Option C (copied `public_html`) | `ln -s ~/beercheck/storage/app/public ~/public_html/storage`. `artisan storage:link` would create the link in the wrong folder here, because the CLI doesn't know about `public_html`. |
| No symlinks at all | Skip the link and write uploads straight into the web root: `PUBLIC_DISK_ROOT=/home/USER/public_html/storage` and `PUBLIC_DISK_URL=https://beercheck.com.au/storage`. Then `mkdir -p ~/public_html/storage/photos`. |

Check it works: upload a photo, then open the image URL from the venue page.

## 6. Cron (scheduler + queue)

cPanel → *Cron Jobs* → add **one** entry, every minute (`* * * * *`):

```
cd /home/USER/beercheck && /usr/local/bin/ea-php84 artisan schedule:run >> /dev/null 2>&1
```

(On CloudLinux alt-php use `/opt/alt/php84/usr/bin/php`. Run `ls /usr/local/bin/ea-php*` to see the
available binaries.)

That one entry drives everything in `routes/console.php`:

| Task | Schedule |
|---|---|
| `app:sync-venues` (NSW + VIC licence import) | Sundays 02:00 AEST (`Australia/Brisbane`, no DST) |
| `queue:work --stop-when-empty --max-time=50` | every minute, no overlap. Processes emails and admin-triggered syncs. |
| `queue:prune-failed`, `model:prune` | daily |
| `auth:clear-resets` | every 15 minutes |

The server timezone does not matter. Each scheduled task carries its own timezone, and the app stores
UTC.

To test by hand:
```bash
/usr/local/bin/ea-php84 artisan schedule:list
/usr/local/bin/ea-php84 artisan app:sync-venues --source=vic_liquor --dry-run
```

## 7. Updating

```bash
cd ~/beercheck && git pull
# upload fresh public/build/ if frontend changed
PHP=/usr/local/bin/ea-php84 ./deploy/cpanel/deploy.sh
```
`deploy.sh` does the following: maintenance mode → `composer install --no-dev` → migrate → seed beers
(idempotent) → cache config, routes, views and events → `storage:link` → `queue:restart` → bring the
site back up.

**Remember:** after editing `.env`, run `php artisan config:cache` again. Cached config ignores `.env`
until it is rebuilt.

## 8. LiteSpeed notes
- LiteSpeed reads `.htaccess` natively. After editing it, restart PHP from cPanel → *LiteSpeed Web Cache
  Manager* (if present), or wait a couple of minutes for the cache to expire.
- Don't enable LSCache page caching for logged-in pages. If you use the LSCache plugin, exclude `/admin`,
  `/publican`, `/prices`, `/profile` and every route with a session cookie. The app sends no public
  cache headers on HTML.

## 9. Troubleshooting

| Symptom | Fix |
|---|---|
| 500 error, blank page | Check `storage/logs/laravel-*.log`. Also check `chmod` on `storage` and `bootstrap/cache`. |
| `SQLSTATE[42000] … FUNCTION ST_Distance_Sphere does not exist` | MariaDB is too old. Ask the host for 10.11+. |
| Map shows but no venues | Run the sync or add venues in Admin → Venues. Check `/venues/nearby` returns JSON. |
| Photos 404 | The storage link is missing or wrong. See §5. |
| Uploads fail on large photos | Raise `upload_max_filesize` / `post_max_size` in the *MultiPHP INI Editor*. |
| Sync "No feed URL configured" | Set `SYNC_NSW_URL` / `SYNC_VIC_URL` in `.env`, then run `config:cache`. |
| Sync job runs twice | Make sure `DB_QUEUE_RETRY_AFTER` (2000) is longer than the sync takes. |
| Geolocation never prompts | Browsers only allow geolocation on HTTPS. Enable AutoSSL. |

## 10. Automated deploys (GitHub Actions → staging)

`.github/workflows/deploy-staging.yml` runs on every push to `main`, `staging` or the current feature
branch, and can also be started by hand (*Actions → Deploy to staging → Run workflow*). Each run:

1. Runs the test suite. The deploy is skipped if the tests fail.
2. Installs the production PHP packages and builds the Vite assets, so the server needs neither Composer
   nor Node.
3. Packs the release as a `tar.gz` and streams it over SSH to `APP_DIR.incoming`. rsync is not needed
   on the server. `deploy/cpanel/swap-release.sh` then swaps the new release into `APP_DIR`, which
   defaults to `/home/spinwebsites/beercheck.spinstaging.com`. Stale files are removed. These paths are
   never touched: `.env`, `storage/`, `public/storage` and `.well-known/` (AutoSSL). The swap script
   refuses empty, relative, home or `public_html` paths.
4. Runs `deploy/cpanel/remote-deploy.sh` on the server. The script:
   - finds PHP 8.4
   - creates `.env` and `APP_KEY` on the first deploy only
   - re-applies the managed `.env` keys (`APP_URL`, `DB_*`, and so on)
   - migrates the database and seeds the beer list
   - creates the storage link
   - caches config, routes and views
   - restarts the queue
   - installs the `schedule:run` cron line (tagged `# beercheck-scheduler`, so it is never added twice)
5. Smoke-tests `/up` and `/`.

Repository secrets (*Settings → Secrets and variables → Actions*):

| Secret | Value |
|---|---|
| `SSH_HOST` | cPanel server hostname |
| `SSH_PORT` | SSH port (usually `22`) |
| `SSH_USER` | `spinwebsites` |
| `SSH_KEY` | private key authorised under cPanel → *SSH Access* |
| `DB_PASSWORD` | password for `spinwebsites_beercheck` |
| `SSH_KEY_PASSPHRASE` *(optional)* | the key's password, if it was generated with one in cPanel |
| `SSH_KNOWN_HOSTS` *(optional)* | output of `ssh-keyscan -p PORT HOST`, which pins the host key. Without it, the key is fetched on each run. |

The domain's document root must be `beercheck.spinstaging.com/public`, i.e. `APP_DIR/public`.
`APP_URL` forces HTTPS, so AutoSSL must have issued a certificate for the subdomain.
