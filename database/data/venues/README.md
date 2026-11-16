# Bundled liquor licence extracts

Pubs, hotels and bars extracted from the official registers with
`php artisan app:licences:extract {source} {register.xlsx}`, which applies the same filter as the sync
(see `config/beercheck.php` → `sync.sources`). `php artisan app:sync-venues` imports these files
whenever no `SYNC_NSW_URL` / `SYNC_VIC_URL` is configured. The staging and production deploy runs it
with `--no-geocode`, and the weekly scheduled sync geocodes any rows without coordinates.

| File | Source | As at | Rows read | Venues kept |
|---|---|---|---|---|
| `nsw_liquor.csv` | Liquor & Gaming NSW, *Licensed premises data* (premises list) | 1 Nov 2026 | 18,986 | 2,816 |
| `vic_liquor.csv` | Liquor Control Victoria, *Current Victorian Licences by Location* | 30 Sep 2026 | 23,169 | 5,359 |

**Kept:**
- NSW: hotel licences (including "general bar"), small bar licences, and on-premises licences whose
  business type is Bar or Wine bar. Venues must be Current and Trading.
- VIC: General, On-Premises, Late night (general) and Late night (on-premises) licences.
- Both states: bars that hold restaurant-style licences (NSW on-premises, VIC restaurant & café) when
  the venue name says bar, pub, tavern, hotel, brewery, taproom, cocktail and so on. For example
  "Bar Conte Sydney" and "Barrel Below" are kept. Motels, caterers, mobile bars, airport lounges and
  sandwich/noodle/espresso "bars" are excluded (`config/beercheck.php` → `bar_name_pattern`).

**Excluded:** packaged liquor / bottle shops, producers and wholesalers, clubs, restaurant & café
licences, limited and pop-up licences, BYO permits, pre-retail and remote sellers.

**Updating:** download the latest register, re-run the extract command for that state, commit the CSV,
and push. The next deploy imports it. Licences that have disappeared are deactivated (never deleted).

**Attribution:**
- NSW data: © State of New South Wales (Department of Creative Industries, Tourism, Hospitality and
  Sport) 2026, CC BY 4.0.
- VIC data: Liquor Control Victoria.

Both are shown in the site footer and on venue pages.
