<?php

namespace App\Policies;

use App\Models\BeerPrice;
use App\Models\User;

class BeerPricePolicy
{
    /** Publican official reply to a punter review at a venue they manage. */
    public function reply(User $user, BeerPrice $price): bool
    {
        return ! $price->is_venue_listed
            && $price->hasReview()
            && $user->managesVenue($price->venue_id);
    }

    public function moderate(User $user): bool
    {
        return $user->isAdmin();
    }
}
