<?php

namespace App\Models;

use App\Enums\VenueManagerStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class VenueManager extends Pivot
{
    protected $table = 'venue_managers';

    public $incrementing = true;

    protected $fillable = [
        'user_id', 'venue_id', 'business_name', 'business_email', 'abn', 'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => VenueManagerStatus::class,
            'business_email_verified_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
