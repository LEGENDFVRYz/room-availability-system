<?php

namespace App\Enums;

enum RoomType: string
{
    case Classroom = 'classroom';
    case Laboratory = 'laboratory';
    case Office = 'office';
    case SpecialRoom = 'special_room';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            RoomType::Classroom   => 'Classroom',
            RoomType::Laboratory  => 'Laboratory',
            RoomType::Office      => 'Office',
            RoomType::SpecialRoom => 'Special Room',
            RoomType::Other       => 'Other',
        };
    }
}
