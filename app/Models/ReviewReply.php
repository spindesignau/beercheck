<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReviewReply extends Model
{
    use SoftDeletes;

    protected $fillable = ['reply_text'];

    public function beerPrice(): BelongsTo
    {
        return $this->belongsTo(BeerPrice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
