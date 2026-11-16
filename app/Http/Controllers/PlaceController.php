<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Suburb / postcode -> coordinates, for people who can't (or won't) share their
 * location. Uses the centre of the venues we already know in that suburb, so no
 * external geocoding service is involved.
 */
class PlaceController extends Controller
{
    public function lookup(Request $request): JsonResponse
    {
        $q = trim((string) $request->validate(['q' => ['required', 'string', 'min:2', 'max:60']])['q']);

        $query = Venue::query()->active()
            ->whereNotNull('suburb')
            ->selectRaw('suburb, state, postcode, COUNT(*) AS venues')
            ->groupBy('suburb', 'state', 'postcode')
            ->orderByDesc('venues')
            ->limit(8);

        preg_match('/^\d{3,4}$/', $q)
            ? $query->where('postcode', 'like', $q.'%')
            : $query->where('suburb', 'like', str_replace(['%', '_'], ['\%', '\_'], $q).'%');

        return response()->json($query->get()->map(function ($p) {
            // Median, not average: a few register entries are pinned far from their suburb.
            $coords = Venue::query()->active()
                ->where('suburb', $p->suburb)->where('state', $p->state)->where('postcode', $p->postcode)
                ->get(['latitude', 'longitude']);

            return [
                'label' => trim("{$p->suburb} {$p->state} {$p->postcode}"),
                'lat' => round(self::median($coords->pluck('latitude')->all()), 6),
                'lng' => round(self::median($coords->pluck('longitude')->all()), 6),
                'venues' => (int) $p->venues,
            ];
        }));
    }

    private static function median(array $values): float
    {
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 ? (float) $values[$mid] : ((float) $values[$mid - 1] + (float) $values[$mid]) / 2;
    }
}
