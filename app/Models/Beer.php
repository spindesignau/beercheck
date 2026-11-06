<?php

namespace App\Models;

use App\Enums\BeerStatus;
use Database\Factories\BeerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Beer extends Model
{
    /** @use HasFactory<BeerFactory> */
    use HasFactory;

    protected $fillable = ['brand', 'name', 'type_id', 'abv'];

    protected function casts(): array
    {
        return [
            'status' => BeerStatus::class,
            'abv' => 'float',
            'reviewed_at' => 'datetime',
        ];
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('beers.status', BeerStatus::Approved);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';
        $concat = $query->getConnection()->getDriverName() === 'sqlite'
            ? "(beers.brand || ' ' || beers.name)"
            : "CONCAT(beers.brand, ' ', beers.name)";

        return $query->where(fn (Builder $q) => $q
            ->where('beers.brand', 'like', $like)
            ->orWhere('beers.name', 'like', $like)
            ->orWhereRaw("{$concat} like ?", [$like]));
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(BeerType::class, 'type_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(BeerPrice::class);
    }

    public function displayName(): string
    {
        return str_starts_with(mb_strtolower($this->name), mb_strtolower($this->brand))
            ? $this->name
            : $this->brand.' '.$this->name;
    }
}
