<?php

namespace App\Models;

use App\Enums\PointsEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit ledger. users.points is a denormalised running total that
 * must always equal SUM(points_events.points_awarded) for the user.
 */
class PointsEvent extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'event_type' => PointsEventType::class,
            'points_awarded' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
