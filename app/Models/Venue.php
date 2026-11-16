<?php

namespace App\Models;

use App\Enums\VenueManagerStatus;
use App\Enums\VenueSource;
use App\Support\Geo;
use Database\Factories\VenueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    /** @use HasFactory<VenueFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'address', 'suburb', 'state', 'postcode', 'latitude', 'longitude',
        'phone', 'website', 'description', 'opening_hours',
        'source', 'external_id', 'licence_type', 'is_active', 'last_synced_at',
    ];

    /** Never serialise the binary POINT column. */
    protected $hidden = ['location'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'opening_hours' => 'array',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'source' => VenueSource::class,
        ];
    }

    protected static function booted(): void
    {
        // Keep the spatial POINT column in step with latitude/longitude.
        static::saving(function (Venue $venue) {
            if (Geo::supportsSpatial($venue->getConnectionName())
                && ($venue->isDirty(['latitude', 'longitude']) || ! $venue->exists)) {
                $venue->attributes['location'] = Geo::pointExpression((float) $venue->latitude, (float) $venue->longitude);
            }
        });

        // Drop the Expression object after save so later saves don't resend it
        // (the binary POINT is also $hidden from arrays/JSON).
        static::saved(function (Venue $venue) {
            unset($venue->attributes['location'], $venue->original['location']);
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('venues.is_active', true);
    }

    /** Words that never help find a venue ("Bar Conte in Clarence Street, Sydney city"). */
    private const SEARCH_STOPWORDS = [
        'in', 'at', 'on', 'of', 'the', 'and', 'near', 'city', 'cbd',
        'street', 'st', 'road', 'rd', 'avenue', 'ave', 'lane', 'ln', 'parade', 'pde', 'place', 'pl',
        'drive', 'dr', 'highway', 'hwy', 'terrace', 'tce', 'boulevard', 'blvd', 'way', 'nsw', 'vic',
    ];

    /** @return list<string> */
    public static function searchWords(string $term): array
    {
        $words = preg_split('/[^\p{L}\p{N}&]+/u', mb_strtolower($term), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_unique(array_filter(
            $words,
            fn ($w) => mb_strlen($w) >= 2 && ! in_array($w, self::SEARCH_STOPWORDS, true),
        )));

        // A search made only of "stopwords" (e.g. "The Bar") still needs something to match.
        return $words ?: array_values(array_filter(
            preg_split('/[^\p{L}\p{N}&]+/u', mb_strtolower($term), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            fn ($w) => mb_strlen($w) >= 2,
        ));
    }

    /**
     * Word-by-word venue search across name, address, suburb and postcode.
     * Every word is matched separately; a venue must match most of the words
     * (all of them for 1-2 word searches) and results are ranked by how many
     * words matched and where (name > suburb/postcode > address).
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $words = self::searchWords($term);
        if (! $words) {
            return $query->whereRaw('1 = 0');
        }

        if (empty($query->getQuery()->columns)) {
            $query->select($query->getModel()->getTable().'.*');
        }

        $matched = [];
        $matchedBindings = [];
        $score = [];
        $scoreBindings = [];
        foreach ($words as $w) {
            $like = '%'.$w.'%'; // words contain only letters, digits and & — nothing to escape
            if (preg_match('/^\d{4}$/', $w)) {
                $matched[] = '(CASE WHEN venues.postcode = ? OR venues.address LIKE ? THEN 1 ELSE 0 END)';
                array_push($matchedBindings, $w, $like);
                $score[] = '(CASE WHEN venues.postcode = ? THEN 2 ELSE 0 END)';
                $scoreBindings[] = $w;
            } else {
                $matched[] = '(CASE WHEN venues.name LIKE ? OR venues.suburb LIKE ? OR venues.address LIKE ? THEN 1 ELSE 0 END)';
                array_push($matchedBindings, $like, $like, $like);
                $score[] = '(CASE WHEN venues.name LIKE ? THEN 3 WHEN venues.suburb LIKE ? THEN 2 WHEN venues.address LIKE ? THEN 1 ELSE 0 END)';
                array_push($scoreBindings, $like, $like, $like);
            }
        }
        $matchedSql = '('.implode(' + ', $matched).')';
        $scoreSql = '('.implode(' + ', $score).')';
        // Only filler words typed (e.g. "the lane")? Then every one of them must match.
        $onlyStopwords = ! array_diff($words, self::SEARCH_STOPWORDS);
        $needed = ($onlyStopwords || count($words) <= 2) ? count($words) : (int) ceil(count($words) * 0.6);

        return $query
            ->selectRaw("{$matchedSql} AS search_matched", $matchedBindings)
            ->selectRaw("{$scoreSql} AS search_score", $scoreBindings)
            ->whereRaw("{$matchedSql} >= ?", [...$matchedBindings, $needed])
            ->orderByDesc('search_matched')
            ->orderByDesc('search_score');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(BeerPrice::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(VenueManager::class);
    }

    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'venue_managers')
            ->using(VenueManager::class)
            ->withPivot(['id', 'status', 'business_name'])
            ->wherePivot('status', VenueManagerStatus::Approved->value)
            ->withTimestamps();
    }

    public function fullAddress(): string
    {
        return collect([$this->address, $this->suburb, trim($this->state.' '.$this->postcode)])
            ->filter()->implode(', ');
    }
}
