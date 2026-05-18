<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomUsageLog extends Model
{
    use HasFactory;

    protected $table = 'tbl_room_usage_logs';

    protected $fillable = [
        'room_id',
        'usage_date',
        'source',
        'schedule_id',
        'schedule_exception_id',
        'room_override_id',
        'subject_code',
        'subject_title',
        'section',
        'instructor_name',
        'status',
        'expected_start',
        'expected_end',
        'actual_start',
        'actual_end',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'usage_date'   => 'date',
            'actual_start' => 'datetime',
            'actual_end'   => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function scheduleException(): BelongsTo
    {
        return $this->belongsTo(ScheduleException::class);
    }

    public function roomOverride(): BelongsTo
    {
        return $this->belongsTo(RoomOverride::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
