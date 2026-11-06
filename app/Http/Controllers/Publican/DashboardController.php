<?php

namespace App\Http\Controllers\Publican;

use App\Http\Controllers\Controller;
use App\Models\BeerPrice;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $venueIds = $user->isAdmin() ? [] : $user->managedVenues()->pluck('venues.id')->all();

        $venues = Venue::query()->whereIn('id', $venueIds)->orderBy('name')->get();

        // Reviews awaiting an official reply.
        $reviews = BeerPrice::query()->visible()
            ->whereIn('venue_id', $venueIds)
            ->where('is_venue_listed', false)
            ->where(fn ($q) => $q->whereNotNull('review_text')->orWhereNotNull('rating'))
            ->with(['beer:id,brand,name', 'user:id,username', 'venue:id,name', 'reply'])
            ->latest()
            ->paginate(20);

        return view('publican.dashboard', compact('venues', 'reviews'));
    }
}
