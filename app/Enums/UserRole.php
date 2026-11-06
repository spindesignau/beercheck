<?php

namespace App\Enums;

enum UserRole: string
{
    case Punter = 'punter';
    case Publican = 'publican';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Punter => 'Punter',
            self::Publican => 'Publican',
            self::Admin => 'Administrator',
        };
    }
}
