<?php

namespace App\Services\Sync;

use App\Enums\VenueSource;

/**
 * A source of raw licence rows. Each row is an associative array keyed by
 * normalised header names (lower_snake_case).
 */
interface VenueFeed
{
    public function source(): VenueSource;

    /** @return iterable<int, array<string, string|null>> */
    public function rows(): iterable;
}
