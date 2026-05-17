<?php

namespace App\Enums;

enum ScheduleExceptionType: string
{
    case Cancellation = 'cancellation';
    case RoomChange = 'room_change';
    case SpecialClass = 'special_class';
    case MakeupClass = 'makeup_class';

    public function label(): string
    {
        return match ($this) {
            self::Cancellation => 'Cancellation',
            self::RoomChange => 'Room Change',
            self::SpecialClass => 'Special Class',
            self::MakeupClass => 'Makeup Class',
        };
    }
}
