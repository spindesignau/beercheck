<?php

namespace App\Http\Controllers;

use App\Models\Beer;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One search box for everything: matching pubs (-> venue page) and matching
 * beers (-> venues ordered by cheapest price for that beer).
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim((string) $request->input('q', ''));

        $venues = collect();
        $beers = collect();
        if (mb_strlen($term) >= 2) {
            $venues = Venue::query()->active()->search($term)->orderBy('name')->limit(15)
                ->get(['id', 'name', 'address', 'suburb', 'state', 'postcode']);
            $beers = Beer::approved()->search($term)->with('type:id,name')->orderBy('brand')->orderBy('name')->limit(15)
                ->get(['id', 'brand', 'name', 'type_id', 'abv']);
        }

        return view('search', compact('term', 'venues', 'beers'));
    }
}
