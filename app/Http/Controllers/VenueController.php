<?php

namespace App\Http\Controllers;

use App\Http\Requests\GeoSearchRequest;
use App\Models\BeerPrice;
use App\Models\Venue;
use App\Services\DiscoveryService;
use App\Support\Geo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Number;
use Illuminate\View\View;

class VenueController extends Controller
{
    public function __construct(private readonly DiscoveryService $discovery) {}

    /**
     * GET /venues/nearby?lat=&lng=&radius=&size=
     * Venues within the radius, nearest first, each with its cheapest current price.
     */
    public function nearby(GeoSearchRequest $request): JsonResponse
    {
        $f = $request->filters();

        $venues = $this->discovery->nearbyVenues($f->lat, $f->lng, $f->radiusKm, $f->size);

        return response()->json([
            'centre' => ['lat' => $f->lat, 'lng' => $f->lng],
            'radius_km' => $f->radiusKm,
            'count' => $venues->count(),
            'venues' => $venues->map(fn (Venue $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'suburb' => $v->suburb,
                'address' => $v->fullAddress(),
                'lat' => $v->latitude,
                'lng' => $v->longitude,
                'distance_m' => (int) round($v->distance_m),
                'url' => route('venues.show', $v),
                'cheapest' => ($c = $v->getAttribute('cheapest')) ? [
                    'price' => (float) $c->price,
                    'price_formatted' => Number::currency($c->price, 'AUD', 'en_AU'),
                    'size' => $c->size,
                    'beer' => trim($c->brand.' '.$c->beer_name),
                    'is_venue_listed' => (bool) $c->is_venue_listed,
                ] : null,
            ])->values(),
        ]);
    }

    /**
     * Flowchart: Search bar -> "search for specific venue -> come up with its info".
     * Single match redirects straight to the venue page.
     */
    public function search(Request $request): RedirectResponse
    {
        $term = trim((string) $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']])['q']);

        $venues = Venue::query()->active()->search($term)
            ->orderBy('name')->limit(50)
            ->get(['id', 'name', 'address', 'suburb', 'state', 'postcode']);

        if ($venues->count() === 1) {
            return redirect()->route('venues.show', $venues->first());
        }

        return redirect()->route('search', ['q' => $term]);
    }

    /** Typeahead for the price form. */
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'lat' => ['nullable', 'numeric'], 'lng' => ['nullable', 'numeric'],
        ]);

        $venues = Venue::query()->active()->search($data['q'])
            ->limit(15)->get(['id', 'name', 'suburb', 'state', 'postcode', 'latitude', 'longitude']);

        if (isset($data['lat'], $data['lng'])) {
            $venues = $venues->sortBy(fn ($v) => Geo::haversine($data['lat'], $data['lng'], $v->latitude, $v->longitude))->values();
        }

        return response()->json($venues->map(fn ($v) => [
            'id' => $v->id, 'label' => "{$v->name} — {$v->suburb} {$v->state} {$v->postcode}",
        ]));
    }

    /** Flowchart: click on venue -> punter price & venue listed price, photos & reviews. */
    public function show(Request $request, Venue $venue): View
    {
        abort_unless($venue->is_active || $request->user()?->isAdmin(), 404);

        $lat = $request->float('lat') ?: null;
        $lng = $request->float('lng') ?: null;

        $current = $this->discovery->currentPrices(fn ($q) => $q->where('beer_prices.venue_id', $venue->id))
            ->orderBy('cp.price')->get();

        $feed = BeerPrice::query()
            ->visible()
            ->where('venue_id', $venue->id)
            ->with(['beer:id,brand,name', 'user:id,username', 'reply.user:id,username'])
            ->latest()
            ->paginate(20);

        $ratings = BeerPrice::query()->visible()->where('venue_id', $venue->id)->whereNotNull('rating')
            ->selectRaw('AVG(rating) AS avg_rating, COUNT(*) AS rating_count')->first();

        return view('venues.show', [
            'venue' => $venue,
            'currentPrices' => $current,
            'feed' => $feed,
            'avgRating' => $ratings?->avg_rating ? round((float) $ratings->avg_rating, 1) : null,
            'ratingCount' => (int) ($ratings?->rating_count ?? 0),
            'distanceM' => ($lat && $lng) ? Geo::haversine($lat, $lng, $venue->latitude, $venue->longitude) : null,
            'canManage' => (bool) $request->user()?->can('manage', $venue),
        ]);
    }
}
