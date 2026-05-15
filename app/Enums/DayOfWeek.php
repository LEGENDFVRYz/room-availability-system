<?php

namespace App\Enums;

enum DayOfWeek: int
{
    case Monday    = 1;
    case Tuesday   = 2;
    case Wednesday = 3;
    case Thursday  = 4;
    case Friday    = 5;
    case Saturday  = 6;
    case Sunday    = 7;

    public function label(): string
    {
        return match ($this) {
            DayOfWeek::Monday    => 'Monday',
            DayOfWeek::Tuesday   => 'Tuesday',
            DayOfWeek::Wednesday => 'Wednesday',
            DayOfWeek::Thursday  => 'Thursday',
            DayOfWeek::Friday    => 'Friday',
            DayOfWeek::Saturday  => 'Saturday',
            DayOfWeek::Sunday    => 'Sunday',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            DayOfWeek::Monday    => 'Mon',
            DayOfWeek::Tuesday   => 'Tue',
            DayOfWeek::Wednesday => 'Wed',
            DayOfWeek::Thursday  => 'Thu',
            DayOfWeek::Friday    => 'Fri',
            DayOfWeek::Saturday  => 'Sat',
            DayOfWeek::Sunday    => 'Sun',
        };
    }
}
