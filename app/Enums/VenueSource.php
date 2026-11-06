<?php

namespace App\Enums;

enum VenueSource: string
{
    case NswLiquor = 'nsw_liquor';
    case VicLiquor = 'vic_liquor';
    case Manual = 'manual';

    public function state(): ?string
    {
        return match ($this) {
            self::NswLiquor => 'NSW',
            self::VicLiquor => 'VIC',
            self::Manual => null,
        };
    }
}
