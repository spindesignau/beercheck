<?php

namespace App\Http\Controllers\Publican;

use App\Enums\VenueManagerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\VenueClaimRequest;
use App\Models\Venue;
use App\Models\VenueManager;
use App\Notifications\VerifyBusinessEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

/**
 * Publican verification flow:
 *   1. user lodges claim (business name/email + venue)       -> pending
 *   2. user clicks signed link sent to the business email     -> email_verified
 *   3. admin approves                                         -> approved (+ role = publican)
 */
class ClaimController extends Controller
{
    public function create(Request $request): View
    {
        return view('publican.claim', [
            'venue' => $request->filled('venue') ? Venue::find($request->integer('venue')) : null,
        ]);
    }

    public function store(VenueClaimRequest $request): RedirectResponse
    {
        $claim = new VenueManager($request->validated());
        $claim->user_id = $request->user()->id;
        $claim->status = VenueManagerStatus::Pending;
        $claim->save();

        Notification::route('mail', $claim->business_email)->notify(new VerifyBusinessEmail($claim->load('venue')));

        return redirect()->route('profile')->with('status', "We've emailed {$claim->business_email}. Click the link in that email to confirm your claim.");
    }

    public function verify(Request $request, VenueManager $claim): RedirectResponse
    {
        abort_unless($claim->user_id === $request->user()->id, 403);

        if ($claim->status === VenueManagerStatus::Pending) {
            $claim->forceFill([
                'status' => VenueManagerStatus::EmailVerified,
                'business_email_verified_at' => now(),
            ])->save();
        }

        return redirect()->route('profile')->with('status', 'Business email confirmed. An administrator will review your claim shortly.');
    }
}
