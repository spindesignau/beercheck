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

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            if (preg_match('/^\d{4}$/', $term)) {
                $q->where('postcode', $term);

                return;
            }
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $q->where('name', 'like', $like)->orWhere('suburb', 'like', $like);
        });
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
