<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;

class VenuePolicy
{
    /** Edit profile details and post official (venue-listed) prices. */
    public function manage(User $user, Venue $venue): bool
    {
        return $user->managesVenue($venue);
    }
}
