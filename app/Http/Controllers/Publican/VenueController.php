<?php

namespace App\Http\Controllers\Publican;

use App\Enums\BeerSize;
use App\Http\Controllers\Controller;
use App\Http\Requests\VenueProfileRequest;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VenueController extends Controller
{
    public function edit(Venue $venue): View
    {
        Gate::authorize('manage', $venue);

        return view('publican.venue-edit', [
            'venue' => $venue,
            'days' => VenueProfileRequest::DAYS,
            'sizes' => BeerSize::cases(),
        ]);
    }

    public function update(VenueProfileRequest $request, Venue $venue): RedirectResponse
    {
        $data = $request->validated();
        $data['opening_hours'] = array_filter($data['opening_hours'] ?? [], 'filled') ?: null;

        $venue->fill($data)->save();

        return redirect()->route('publican.venues.edit', $venue)->with('status', 'Venue details updated.');
    }
}
