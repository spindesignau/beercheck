<?php

namespace App\Http\Controllers\Publican;

use App\Http\Controllers\Controller;
use App\Http\Requests\OfficialPriceRequest;
use App\Models\Venue;
use App\Services\PriceSubmissionService;
use Illuminate\Http\RedirectResponse;

/** Official, venue-listed prices (is_venue_listed = true). No points are awarded. */
class OfficialPriceController extends Controller
{
    public function store(OfficialPriceRequest $request, Venue $venue, PriceSubmissionService $submissions): RedirectResponse
    {
        $submissions->submit(
            $request->user(),
            [...$request->safe()->except('photo'), 'venue_id' => $venue->id],
            $request->file('photo'),
            asVenueListing: true,
        );

        return back()->with('status', 'Official price published.');
    }
}
