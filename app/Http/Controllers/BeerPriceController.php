<?php

namespace App\Http\Controllers;

use App\Enums\BeerSize;
use App\Http\Requests\StoreBeerPriceRequest;
use App\Models\Venue;
use App\Services\PriceSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Flowchart: camera button -> take photo -> location, review, price -> points. */
class BeerPriceController extends Controller
{
    public function create(Request $request): View
    {
        $venue = $request->filled('venue')
            ? Venue::query()->active()->find($request->integer('venue'), ['id', 'name', 'suburb', 'state', 'postcode'])
            : null;

        return view('prices.create', [
            'venue' => $venue,
            'sizes' => BeerSize::cases(),
            'points' => config('beercheck.points'),
        ]);
    }

    public function store(StoreBeerPriceRequest $request, PriceSubmissionService $submissions): RedirectResponse
    {
        ['price' => $price, 'points' => $points] = $submissions->submit(
            $request->user(),
            $request->safe()->except('photo'),
            $request->file('photo'),
        );

        $message = $points > 0
            ? "Price posted — you earned {$points} points!"
            : 'Price posted. (No points for repeat posts of the same beer within '
                .config('beercheck.points.repeat_cooldown_hours').' hours.)';

        return redirect()->route('venues.show', $price->venue_id)->with('status', $message);
    }
}
