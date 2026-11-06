<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VenueSource;
use App\Http\Controllers\Controller;
use App\Jobs\SyncVenuesJob;
use App\Models\VenueSyncRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SyncController extends Controller
{
    public function index(): View
    {
        return view('admin.sync', [
            'runs' => VenueSyncRun::with('triggeredBy:id,username')->latest()->paginate(20),
            'sources' => collect(config('beercheck.sync.sources'))->map(fn ($c, $key) => [
                'key' => $key, 'enabled' => (bool) $c['enabled'], 'configured' => filled($c['url']),
            ]),
        ]);
    }

    /** Queued: picked up by the per-minute cron queue worker. */
    public function run(Request $request): RedirectResponse
    {
        $data = $request->validate(['source' => ['required', Rule::in([VenueSource::NswLiquor->value, VenueSource::VicLiquor->value])]]);

        SyncVenuesJob::dispatch(VenueSource::from($data['source']), $request->user()->id);

        return back()->with('status', "Sync for {$data['source']} queued. It will start within a minute.");
    }
}
