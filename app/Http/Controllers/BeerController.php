<?php

namespace App\Http\Controllers;

use App\Enums\BeerSize;
use App\Enums\BeerStatus;
use App\Http\Requests\GeoSearchRequest;
use App\Http\Requests\StoreBeerRequest;
use App\Models\Beer;
use App\Models\BeerType;
use App\Services\DiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Number;
use Illuminate\View\View;

class BeerController extends Controller
{
    public function __construct(private readonly DiscoveryService $discovery) {}

    /**
     * GET /beers/cheapest
     *
     * Two modes, same engine:
     *  - Beer search (?q=carlton): venues ordered by cheapest current price for matching beers.
     *  - Filter engine (brand/type/size/price/radius): the "Top 3 cheapest" nearby.
     *
     * Responds with JSON for XHR (map/list refresh) and HTML otherwise.
     */
    public function cheapest(GeoSearchRequest $request): View|JsonResponse
    {
        $term = trim((string) $request->validated('q'));
        $overrides = [];
        $limit = config('beercheck.prices.top_n');

        if ($term !== '') {
            $overrides['beer_ids'] = Beer::approved()->search($term)->limit(100)->pluck('id')->all();
            $limit = 50;
        }

        $filters = $request->filters($overrides);
        $results = $this->discovery->cheapest($filters, $limit);

        $payload = $results->map(fn ($r) => [
            'price' => $r->price,
            'price_formatted' => Number::currency($r->price, 'AUD', 'en_AU'),
            'size' => $r->size->value,
            'size_label' => $r->size->label(),
            'beer' => trim($r->brand.' '.$r->beer_name),
            'is_venue_listed' => (bool) $r->is_venue_listed,
            'posted_at' => $r->created_at,
            'venue' => [
                'id' => $r->venue_id, 'name' => $r->venue_name, 'suburb' => $r->suburb,
                'lat' => (float) $r->latitude, 'lng' => (float) $r->longitude,
                'url' => route('venues.show', $r->venue_id),
            ],
            'distance_m' => (int) round($r->distance_m),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['mode' => $term !== '' ? 'search' : 'top', 'results' => $payload]);
        }

        return view('beers.cheapest', [
            'results' => $payload,
            'term' => $term,
            'filters' => $filters,
            'types' => BeerType::ordered(),
            'sizes' => BeerSize::cases(),
            'brands' => Beer::approved()->distinct()->orderBy('brand')->pluck('brand'),
            'hasLocation' => $request->filled(['lat', 'lng']),
        ]);
    }

    /** Typeahead for the price form (approved beers only). */
    public function lookup(Request $request): JsonResponse
    {
        $q = $request->validate(['q' => ['required', 'string', 'min:1', 'max:100']])['q'];

        return response()->json(
            Beer::approved()->search($q)->orderBy('brand')->orderBy('name')->limit(20)->get(['id', 'brand', 'name'])
                ->map(fn (Beer $b) => ['id' => $b->id, 'label' => $b->displayName()])
        );
    }

    public function create(): View
    {
        return view('beers.create', ['types' => BeerType::ordered()]);
    }

    /** Punter suggests a missing beer -> pending; +15 points if an admin approves it. */
    public function store(StoreBeerRequest $request): RedirectResponse
    {
        $beer = new Beer($request->validated());
        $beer->status = BeerStatus::Pending;
        $beer->submitted_by = $request->user()->id;
        $beer->save();

        return redirect()->route('profile')
            ->with('status', "Thanks! \"{$beer->displayName()}\" has been sent for review. You'll earn "
                .config('beercheck.points.beer_approved').' points if it is approved.');
    }
}
