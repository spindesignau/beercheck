<?php

namespace App\Http\Controllers\Publican;

use App\Http\Controllers\Controller;
use App\Models\BeerPrice;
use App\Models\ReviewReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReviewReplyController extends Controller
{
    public function upsert(Request $request, BeerPrice $beerPrice): RedirectResponse
    {
        Gate::authorize('reply', $beerPrice);
        $data = $request->validate(['reply_text' => ['required', 'string', 'min:2', 'max:2000']]);

        $reply = ReviewReply::withTrashed()->where('beer_price_id', $beerPrice->id)->first() ?? new ReviewReply;
        $reply->beer_price_id = $beerPrice->id;
        $reply->fill($data);
        $reply->user_id = $request->user()->id;
        $reply->deleted_at = null;
        $reply->save();

        return back()->with('status', 'Reply published.');
    }

    public function destroy(BeerPrice $beerPrice): RedirectResponse
    {
        Gate::authorize('reply', $beerPrice);
        $beerPrice->reply()->delete();

        return back()->with('status', 'Reply removed.');
    }
}
