<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BeerPrice;
use App\Services\ImageService;
use App\Services\PointsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Moderation of punter posts (price + photo + rating + review are one record).
 *  - flag:   hide from public pending review (reversible)
 *  - remove photo: delete the file only, keep the price
 *  - delete: soft delete + claw back the points that post earned
 */
class PriceModerationController extends Controller
{
    public function __construct(private readonly PointsService $points) {}

    public function index(Request $request): View
    {
        $view = $request->query('view', 'recent');

        $query = BeerPrice::query()->with(['beer:id,brand,name', 'venue:id,name,suburb', 'user:id,username']);
        $query = match ($view) {
            'flagged' => $query->where('is_flagged', true),
            'photos' => $query->whereNotNull('photo_path'),
            'deleted' => $query->onlyTrashed(),
            default => $query,
        };

        return view('admin.prices', ['prices' => $query->latest()->paginate(30)->withQueryString(), 'view' => $view]);
    }

    public function flag(Request $request, BeerPrice $beerPrice): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $this->moderate($beerPrice, $request, ['is_flagged' => true, 'flag_reason' => $data['reason'] ?? null]);

        return back()->with('status', 'Post hidden.');
    }

    public function unflag(Request $request, BeerPrice $beerPrice): RedirectResponse
    {
        $this->moderate($beerPrice, $request, ['is_flagged' => false, 'flag_reason' => null]);

        return back()->with('status', 'Post restored to public view.');
    }

    public function removePhoto(Request $request, BeerPrice $beerPrice, ImageService $images): RedirectResponse
    {
        $images->delete($beerPrice->photo_path);
        $this->moderate($beerPrice, $request, ['photo_path' => null]);

        return back()->with('status', 'Photo removed.');
    }

    public function destroy(Request $request, BeerPrice $beerPrice): RedirectResponse
    {
        DB::transaction(function () use ($request, $beerPrice) {
            $this->moderate($beerPrice, $request, []);
            $beerPrice->delete();
            $this->points->reverse($beerPrice, $request->user(), 'Post removed by moderator');
        });

        return back()->with('status', 'Post removed and points reversed.');
    }

    public function restore(Request $request, BeerPrice $beerPrice): RedirectResponse
    {
        $beerPrice->restore();
        $this->moderate($beerPrice, $request, []);

        return back()->with('status', 'Post restored (points are not re-awarded automatically).');
    }

    private function moderate(BeerPrice $price, Request $request, array $changes): void
    {
        $price->forceFill([...$changes, 'moderated_by' => $request->user()->id, 'moderated_at' => now()])->save();
    }
}
