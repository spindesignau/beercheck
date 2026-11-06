<?php

namespace App\Enums;

enum BeerSize: string
{
    case Pot = 'pot';
    case Schooner = 'schooner';
    case Pint = 'pint';
    case Jug = 'jug';
    case Stubby = 'stubby';
    case Longneck = 'longneck';
    case Can = 'can';

    /** Nominal volume in millilitres (used for display / $ per litre). */
    public function millilitres(): int
    {
        return match ($this) {
            self::Pot => 285,
            self::Schooner => 425,
            self::Pint => 570,
            self::Jug => 1140,
            self::Stubby => 375,
            self::Longneck => 750,
            self::Can => 375,
        };
    }

    public function label(): string
    {
        return ucfirst($this->value).' ('.$this->millilitres().'ml)';
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
