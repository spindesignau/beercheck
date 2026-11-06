<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VenueSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminVenueRequest;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VenueController extends Controller
{
    public function index(Request $request): View
    {
        $venues = Venue::query()
            ->when($request->filled('q'), fn ($q) => $q->search($request->string('q')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->string('source')))
            ->when($request->query('active') !== null && $request->query('active') !== '', fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate(50, ['id', 'name', 'suburb', 'state', 'postcode', 'source', 'licence_type', 'is_active', 'deactivation_reason', 'last_synced_at'])
            ->withQueryString();

        return view('admin.venues.index', ['venues' => $venues, 'sources' => VenueSource::cases()]);
    }

    public function create(): View
    {
        return view('admin.venues.form', ['venue' => new Venue(['state' => 'VIC'])]);
    }

    public function store(AdminVenueRequest $request): RedirectResponse
    {
        $venue = new Venue($request->validated());
        $venue->source = VenueSource::Manual;
        $venue->save();

        return redirect()->route('admin.venues.edit', $venue)->with('status', 'Venue created.');
    }

    public function edit(Venue $venue): View
    {
        return view('admin.venues.form', ['venue' => $venue]);
    }

    public function update(AdminVenueRequest $request, Venue $venue): RedirectResponse
    {
        $venue->fill($request->validated())->save();

        return back()->with('status', 'Venue updated. Note: synced fields are overwritten by the next licence sync.');
    }

    /** Admin deactivation is "sticky": the licence sync will not re-activate it. */
    public function toggle(Venue $venue): RedirectResponse
    {
        $active = ! $venue->is_active;
        $venue->forceFill([
            'is_active' => $active,
            'deactivation_reason' => $active ? null : 'admin',
            'deactivated_at' => $active ? null : now(),
        ])->save();

        return back()->with('status', $active ? 'Venue activated.' : 'Venue deactivated.');
    }
}
