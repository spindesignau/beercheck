<?php

namespace App\Http\Controllers;

use App\Enums\BeerSize;
use Illuminate\View\View;

/** Flowchart: Home Screen. Map + list are hydrated client-side from venues.nearby. */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
            'geo' => config('beercheck.geo'),
            'sizes' => BeerSize::cases(),
        ]);
    }
}
