<?php

namespace App\Services;

use App\Data\PriceFilters;
use App\Enums\BeerSize;
use App\Enums\BeerStatus;
use App\Models\Venue;
use App\Support\Geo;
use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * All geo + price discovery queries.
 *
 * "Current price" definition: for each (venue, beer, size) the single most
 * relevant price inside the freshness window — official venue-listed prices
 * win over punter posts, then the most recent post wins. Cheapest-price
 * comparisons are always made against current prices so an old, stale
 * cheap post can never outrank today's real price.
 */
class DiscoveryService
{
    /** Columns selected from venues (never the binary POINT column). */
    private const VENUE_COLUMNS = [
        'venues.id', 'venues.name', 'venues.address', 'venues.suburb', 'venues.state',
        'venues.postcode', 'venues.latitude', 'venues.longitude',
    ];

    /**
     * Ranked "current price" rows (rn = 1) as a query builder over alias `cp`.
     *
     * @param  Closure(Builder): void|null  $constrain  extra WHERE clauses on beer_prices/beers
     *                                                  (only partition-safe filters: venue, beer, size)
     */
    public function currentPrices(?Closure $constrain = null): Builder
    {
        $ranked = DB::table('beer_prices')
            ->join('beers', 'beers.id', '=', 'beer_prices.beer_id')
            ->where('beers.status', BeerStatus::Approved->value)
            ->whereNull('beer_prices.deleted_at')
            ->where('beer_prices.is_flagged', false)
            ->where('beer_prices.created_at', '>=', now()->subDays(config('beercheck.prices.freshness_days')))
            ->select([
                'beer_prices.id', 'beer_prices.venue_id', 'beer_prices.beer_id', 'beer_prices.size',
                'beer_prices.price', 'beer_prices.is_venue_listed', 'beer_prices.created_at',
                'beers.brand', 'beers.name as beer_name', 'beers.type_id',
            ])
            ->selectRaw('ROW_NUMBER() OVER (
                PARTITION BY beer_prices.venue_id, beer_prices.beer_id, beer_prices.size
                ORDER BY beer_prices.is_venue_listed DESC, beer_prices.created_at DESC, beer_prices.id DESC
            ) AS rn');

        if ($constrain) {
            $constrain($ranked);
        }

        return DB::query()->fromSub($ranked, 'cp')->where('cp.rn', 1);
    }

    /**
     * Restrict a venues query to a radius, using the SPATIAL index on MariaDB
     * (MBRContains on a bounding box) or a lat/lng box elsewhere, then the
     * exact great-circle distance. Adds a `distance_m` column.
     */
    public function applyRadius(EloquentBuilder|Builder $query, float $lat, float $lng, float $radiusKm): EloquentBuilder|Builder
    {
        $box = Geo::boundingBox($lat, $lng, $radiusKm);
        [$distanceSql, $bindings] = Geo::distanceSql($lat, $lng);

        if (Geo::supportsSpatial()) {
            $polygon = sprintf(
                'POLYGON((%1$.7F %2$.7F, %3$.7F %2$.7F, %3$.7F %4$.7F, %1$.7F %4$.7F, %1$.7F %2$.7F))',
                $box['minLng'], $box['minLat'], $box['maxLng'], $box['maxLat'],
            );
            $query->whereRaw('MBRContains(ST_GeomFromText(?), venues.location)', [$polygon]);
        } else {
            $query->whereBetween('venues.latitude', [$box['minLat'], $box['maxLat']])
                ->whereBetween('venues.longitude', [$box['minLng'], $box['maxLng']]);
        }

        return $query
            ->selectRaw("{$distanceSql} AS distance_m", $bindings)
            ->whereRaw("{$distanceSql} <= ?", [...$bindings, $radiusKm * 1000]);
    }

    /**
     * Home screen: active venues within the radius, nearest first, each with
     * its cheapest current price (optionally for a given size).
     *
     * @return Collection<int, Venue>
     */
    public function nearbyVenues(float $lat, float $lng, float $radiusKm, ?BeerSize $size = null, ?int $limit = null): Collection
    {
        $limit ??= config('beercheck.geo.max_results');

        $query = Venue::query()->active()->select(self::VENUE_COLUMNS);
        $venues = $this->applyRadius($query, $lat, $lng, $radiusKm)
            ->orderBy('distance_m')
            ->limit($limit)
            ->get();

        if ($venues->isEmpty()) {
            return $venues;
        }

        $cheapest = $this->currentPrices(function (Builder $q) use ($venues, $size) {
            $q->whereIn('beer_prices.venue_id', $venues->modelKeys());
            if ($size) {
                $q->where('beer_prices.size', $size->value);
            }
        })
            ->orderBy('cp.price')
            ->get()
            ->groupBy('venue_id')
            ->map(fn (Collection $rows) => $rows->first());

        return $venues->each(function (Venue $venue) use ($cheapest) {
            $venue->distance_m = (float) $venue->distance_m;
            $venue->setAttribute('cheapest', $cheapest->get($venue->id));
        });
    }

    /**
     * Filter engine / beer search: cheapest current prices matching the filters
     * within the radius, ordered by price then distance.
     */
    public function cheapest(PriceFilters $f, ?int $limit = null): Collection
    {
        $limit ??= config('beercheck.prices.top_n');

        $prices = $this->currentPrices(function (Builder $q) use ($f) {
            if ($f->beerIds !== null) {
                $q->whereIn('beer_prices.beer_id', $f->beerIds ?: [0]);
            }
            if ($f->brand) {
                $q->where('beers.brand', $f->brand);
            }
            if ($f->typeId) {
                $q->where('beers.type_id', $f->typeId);
            }
            if ($f->size) {
                $q->where('beer_prices.size', $f->size->value);
            }
        });

        $query = $prices
            ->join('venues', 'venues.id', '=', 'cp.venue_id')
            ->where('venues.is_active', true)
            ->select([
                'cp.id as price_id', 'cp.price', 'cp.size', 'cp.is_venue_listed', 'cp.created_at',
                'cp.beer_id', 'cp.brand', 'cp.beer_name',
                'venues.id as venue_id', 'venues.name as venue_name', 'venues.suburb',
                'venues.latitude', 'venues.longitude',
            ]);

        // Price range must be applied AFTER ranking (on the current price).
        if ($f->minPrice !== null) {
            $query->where('cp.price', '>=', $f->minPrice);
        }
        if ($f->maxPrice !== null) {
            $query->where('cp.price', '<=', $f->maxPrice);
        }

        return $this->applyRadius($query, $f->lat, $f->lng, $f->radiusKm)
            ->orderBy('cp.price')
            ->orderBy('distance_m')
            ->limit($limit)
            ->get()
            ->map(function (object $row) {
                $row->price = (float) $row->price;
                $row->distance_m = (float) $row->distance_m;
                $row->size = BeerSize::from($row->size);

                return $row;
            });
    }
}
