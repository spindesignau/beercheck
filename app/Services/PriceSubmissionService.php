<?php

namespace App\Services;

use App\Enums\PointsEventType;
use App\Models\BeerPrice;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Camera / price / review workflow:
 *   compress + store photo -> insert price -> calculate points -> write ledger
 * The DB work is one transaction; if it fails the stored photo is removed.
 */
class PriceSubmissionService
{
    public function __construct(
        private readonly ImageService $images,
        private readonly PointsService $points,
    ) {}

    /**
     * @param  array{beer_id:int, venue_id:int, size:string, price:float|string, rating?:int|null, review_text?:string|null}  $data
     * @return array{price: BeerPrice, points: int}
     */
    public function submit(User $user, array $data, ?UploadedFile $photo = null, bool $asVenueListing = false): array
    {
        $photoPath = $photo ? $this->images->storeCompressed($photo) : null;

        try {
            return DB::transaction(function () use ($user, $data, $photoPath, $asVenueListing) {
                $price = new BeerPrice([
                    'beer_id' => $data['beer_id'],
                    'venue_id' => $data['venue_id'],
                    'size' => $data['size'],
                    'price' => $data['price'],
                    // Official listings are prices, not reviews.
                    'rating' => $asVenueListing ? null : ($data['rating'] ?? null),
                    'review_text' => $asVenueListing ? null : (filled($data['review_text'] ?? null) ? trim($data['review_text']) : null),
                ]);
                $price->user_id = $user->id;
                $price->is_venue_listed = $asVenueListing;
                $price->photo_path = $photoPath;
                $price->save();

                $earned = $asVenueListing ? 0 : $this->awardFor($user, $price);

                return ['price' => $price, 'points' => $earned];
            });
        } catch (Throwable $e) {
            $this->images->delete($photoPath);
            throw $e;
        }
    }

    /** Points for a punter submission. Must be called inside the submission transaction. */
    private function awardFor(User $user, BeerPrice $price): int
    {
        if ($this->isRepeatWithinCooldown($user, $price)) {
            return 0;
        }

        $earned = 0;
        $earned += $this->points->award($user, PointsEventType::PricePosted, $price)?->points_awarded ?? 0;

        if ($price->photo_path) {
            $earned += $this->points->award($user, PointsEventType::PhotoUploaded, $price)?->points_awarded ?? 0;
        }

        if (mb_strlen((string) $price->review_text) >= config('beercheck.points.review_min_chars')) {
            $earned += $this->points->award($user, PointsEventType::ReviewWritten, $price)?->points_awarded ?? 0;
        }

        return $earned;
    }

    private function isRepeatWithinCooldown(User $user, BeerPrice $price): bool
    {
        return BeerPrice::withTrashed()
            ->where('user_id', $user->id)
            ->where('venue_id', $price->venue_id)
            ->where('beer_id', $price->beer_id)
            ->where('size', $price->size)
            ->where('id', '!=', $price->id)
            ->where('created_at', '>=', now()->subHours(config('beercheck.points.repeat_cooldown_hours')))
            ->exists();
    }
}
