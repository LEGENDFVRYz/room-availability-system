<?php

namespace App\Enums;

enum RoomOverrideStatus: string
{
    case Maintenance = 'maintenance';
    case Unavailable = 'unavailable';
    case Reserved = 'reserved';

    public function label(): string
    {
        return match ($this) {
            self::Maintenance => 'Maintenance',
            self::Unavailable => 'Unavailable',
            self::Reserved => 'Reserved',
        };
    }
}
