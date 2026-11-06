# 🍺 Beercheck

Beercheck is a mobile-first web app for finding the cheapest beer near you. Punters post prices,
photos and reviews and earn points. Publicans post official prices and reply to reviews. Venues are
imported from the NSW and VIC liquor licence registers.

**Stack:** Laravel 12 · PHP 8.4 · MariaDB (spatial) · Blade + Tailwind + Alpine · Leaflet/OpenStreetMap ·
Intervention Image · runs on cPanel shared hosting.

- 📐 [Technical specification & architecture](docs/SPECIFICATION.md)
- 🚀 [cPanel deployment guide](docs/DEPLOYMENT_CPANEL.md)

## Local development

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite           # or set DB_CONNECTION=mariadb
php artisan migrate --seed               # local env also seeds demo venues around Melbourne CBD
npm install && npm run dev
php artisan serve
```

The demo admin login is `admin@beercheck.test` / `password` (local seed only).

## Tests

```bash
php artisan test                                            # SQLite in-memory
DB_CONNECTION=mariadb DB_DATABASE=beercheck_test DB_USERNAME=… DB_PASSWORD=… php artisan test
```

## Venue sync

```bash
php artisan app:sync-venues --dry-run
php artisan app:sync-venues --source=vic_liquor --file=path/to/licences.csv
```
