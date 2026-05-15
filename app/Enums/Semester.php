<?php

namespace App\Enums;

enum Semester: int
{
    case First  = 1;
    case Second = 2;
    case Summer = 3;

    public function label(): string
    {
        return match ($this) {
            Semester::First  => '1st Semester',
            Semester::Second => '2nd Semester',
            Semester::Summer => 'Summer',
        };
    }
}
