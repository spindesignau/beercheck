<?php

namespace App\Enums;

enum PointsEventType: string
{
    case PricePosted = 'price_posted';
    case PhotoUploaded = 'photo_uploaded';
    case ReviewWritten = 'review_written';
    case BeerApproved = 'beer_approved';
    /** Negative adjustment when an admin removes a contribution. */
    case ModerationReversal = 'moderation_reversal';

    /** Points value comes from config so it can be tuned without a deploy. */
    public function points(): int
    {
        return (int) config('beercheck.points.'.$this->value, 0);
    }
}
