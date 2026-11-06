<?php

namespace App\Http\Controllers;

use App\Enums\BeerSize;
use App\Models\Beer;
use App\Models\BeerType;
use Illuminate\View\View;

/** Flowchart: Home Screen. Map + list are hydrated client-side from venues.nearby. */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
            'geo' => config('beercheck.geo'),
            'priceBounds' => [config('beercheck.prices.filter_min'), config('beercheck.prices.filter_max')],
            'types' => BeerType::ordered(),
            'sizes' => BeerSize::cases(),
            'brands' => Beer::approved()->distinct()->orderBy('brand')->pluck('brand'),
        ]);
    }
}
