<?php

namespace App\Enums;

enum ScheduleExceptionStatus: string
{
    case Pending = 'pending';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case AutoCancelled = 'auto_cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Ongoing => 'Ongoing',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::AutoCancelled => 'Auto-cancelled',
        };
    }
}
