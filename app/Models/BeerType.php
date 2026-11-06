<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BeerType extends Model
{
    protected $fillable = ['name', 'display_order'];

    public function beers(): HasMany
    {
        return $this->hasMany(Beer::class, 'type_id');
    }

    /** @return Collection<int, BeerType> */
    public static function ordered()
    {
        return static::query()->orderBy('display_order')->get();
    }
}
