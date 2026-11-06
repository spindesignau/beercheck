<?php

namespace App\Models;

use App\Enums\BeerSize;
use Database\Factories\BeerPriceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class BeerPrice extends Model
{
    /** @use HasFactory<BeerPriceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * user_id, is_venue_listed and moderation fields are set explicitly by
     * services — never mass assigned from a request.
     */
    protected $fillable = ['beer_id', 'venue_id', 'price', 'size', 'rating', 'review_text'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'size' => BeerSize::class,
            'is_venue_listed' => 'boolean',
            'is_flagged' => 'boolean',
            'rating' => 'integer',
            'moderated_at' => 'datetime',
        ];
    }

    /** Publicly visible rows (not flagged; soft-deleted are excluded by the trait). */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('beer_prices.is_flagged', false);
    }

    public function beer(): BelongsTo
    {
        return $this->belongsTo(Beer::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reply(): HasOne
    {
        return $this->hasOne(ReviewReply::class);
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function hasReview(): bool
    {
        return filled($this->review_text) || $this->rating !== null;
    }
}
