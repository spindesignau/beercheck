<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BeerStatus;
use App\Enums\VenueManagerStatus;
use App\Http\Controllers\Controller;
use App\Models\Beer;
use App\Models\BeerPrice;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueManager;
use App\Models\VenueSyncRun;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'Users' => User::count(),
                'Active venues' => Venue::where('is_active', true)->count(),
                'Prices (7 days)' => BeerPrice::where('created_at', '>=', now()->subDays(7))->count(),
                'Pending beers' => Beer::where('status', BeerStatus::Pending)->count(),
                'Flagged posts' => BeerPrice::where('is_flagged', true)->count(),
                'Claims to review' => VenueManager::where('status', VenueManagerStatus::EmailVerified)->count(),
            ],
            'lastSyncs' => VenueSyncRun::latest()->limit(4)->get(),
        ]);
    }
}
