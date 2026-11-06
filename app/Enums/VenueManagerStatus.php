<?php

namespace App\Enums;

enum VenueManagerStatus: string
{
    /** Claim lodged, business email not yet confirmed. */
    case Pending = 'pending';
    /** Business email confirmed, awaiting admin review. */
    case EmailVerified = 'email_verified';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
