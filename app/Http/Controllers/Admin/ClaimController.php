<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\VenueManagerStatus;
use App\Http\Controllers\Controller;
use App\Models\VenueManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClaimController extends Controller
{
    public function index(): View
    {
        return view('admin.claims', [
            'claims' => VenueManager::query()
                ->with(['user:id,username,email', 'venue:id,name,suburb,phone,website'])
                ->orderByRaw("CASE status WHEN 'email_verified' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
                ->latest()
                ->paginate(30),
        ]);
    }

    public function approve(Request $request, VenueManager $claim): RedirectResponse
    {
        DB::transaction(function () use ($request, $claim) {
            $claim->forceFill([
                'status' => VenueManagerStatus::Approved,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ])->save();

            $user = $claim->user;
            if ($user->role === UserRole::Punter) {
                $user->forceFill(['role' => UserRole::Publican])->save();
            }
        });

        return back()->with('status', 'Claim approved.');
    }

    public function reject(Request $request, VenueManager $claim): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        DB::transaction(function () use ($request, $claim, $data) {
            $claim->forceFill([
                'status' => VenueManagerStatus::Rejected,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'admin_notes' => $data['notes'] ?? null,
            ])->save();

            // Demote publicans left with no approved venues.
            $user = $claim->user;
            if ($user->role === UserRole::Publican && ! $user->venueClaims()->where('status', VenueManagerStatus::Approved)->exists()) {
                $user->forceFill(['role' => UserRole::Punter])->save();
            }
        });

        return back()->with('status', 'Claim rejected.');
    }
}
