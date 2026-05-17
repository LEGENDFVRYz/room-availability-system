<?php

namespace App\Models;

use App\Enums\ScheduleExceptionStatus;
use App\Enums\ScheduleExceptionType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleException extends Model
{
    use HasFactory;

    protected $table = 'tbl_schedule_exceptions';

    protected $fillable = [
        'schedule_id',
        'academic_term_id',
        'room_id',
        'event_date',
        'event_type',
        'start_time',
        'end_time',
        'subject_code',
        'subject_title',
        'section',
        'instructor_name',
        'reason',
        'status',
        'auto_cancel_at',
        'claimed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'event_date'     => 'date',
            'event_type'     => ScheduleExceptionType::class,
            'status'         => ScheduleExceptionStatus::class,
            'auto_cancel_at' => 'datetime',
            'claimed_at'     => 'datetime',
        ];
    }


    // -------------------------------------------------------
    // Relationships
    // -------------------------------------------------------
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }


    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------
    public function scopeForDate(Builder $query, string|Carbon $date): Builder
    {
        return $query->whereDate('event_date', $date);
    }

    public function scopeForRoom(Builder $query, int|Room $room): Builder
    {
        $roomId = $room instanceof Room ? $room->id : $room;

        return $query->where('room_id', $roomId);
    }

    public function scopeForAcademicTerm(Builder $query, int|AcademicTerm $academicTerm): Builder
    {
        $academicTermId = $academicTerm instanceof AcademicTerm
            ? $academicTerm->id
            : $academicTerm;

        return $query->where('academic_term_id', $academicTermId);
    }

    public function scopePendingAutoCancel(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where('status', ScheduleExceptionStatus::Pending->value)
            ->whereNotNull('auto_cancel_at')
            ->where('auto_cancel_at', '<=', $at)
            ->whereNull('claimed_at');
    }

    // filtering purposes
    public function isCancellation(): bool
    {
        return $this->event_type === ScheduleExceptionType::Cancellation;
    }

    public function isRoomChange(): bool
    {
        return $this->event_type === ScheduleExceptionType::RoomChange;
    }

    public function isSpecialClass(): bool
    {
        return $this->event_type === ScheduleExceptionType::SpecialClass;
    }

    public function isMakeupClass(): bool
    {
        return $this->event_type === ScheduleExceptionType::MakeupClass;
    }
}
