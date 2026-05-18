<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\RoomUsageLog;
use App\Models\Schedule;
use App\Models\ScheduleException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyOperationService
{
    public function requestClass(array $data, int $userId): ScheduleException
    {
        $eventDate = Carbon::parse($data['event_date'])->toDateString();
        $term = $this->resolveTermForDate($eventDate);

        if (! $term) {
            throw ValidationException::withMessages([
                'event_date' => 'No academic term is available for the selected date.',
            ]);
        }

        return DB::transaction(function () use ($data, $userId, $eventDate, $term) {
            return ScheduleException::create([
                'schedule_id'      => null,
                'academic_term_id' => $term->id,
                'room_id'          => $data['room_id'],
                'event_date'       => $eventDate,
                'event_type'       => $data['event_type'],
                'start_time'       => $data['start_time'],
                'end_time'         => $data['end_time'],
                'subject_code'     => $data['subject_code'],
                'subject_title'    => $data['subject_title'],
                'section'          => $data['section'],
                'instructor_name'  => $data['instructor_name'] ?? null,
                'reason'           => $data['reason'] ?? null,
                'status'           => 'pending',
                'auto_cancel_at'   => $this->autoCancelAt($eventDate, $data['start_time']),
                'claimed_at'       => null,
                'created_by'       => $userId,
                'updated_by'       => $userId,
            ]);
        });
    }

    public function cancelClass(array $data, int $userId): ScheduleException
    {
        return DB::transaction(function () use ($data, $userId) {
            $eventDate = Carbon::parse($data['event_date'])->toDateString();
            $reason = $data['reason'] ?? null;

            if (! empty($data['exception_id'])) {
                $exception = ScheduleException::findOrFail($data['exception_id']);
                $exception->update([
                    'status'     => 'cancelled',
                    'reason'     => $reason ?: $exception->reason,
                    'updated_by' => $userId,
                ]);

                $this->markExceptionUsage($exception, 'cancelled', $userId);

                return $exception;
            }

            if (empty($data['schedule_id'])) {
                throw ValidationException::withMessages([
                    'schedule_id' => 'A schedule or exception is required.',
                ]);
            }

            $schedule = Schedule::findOrFail($data['schedule_id']);
            $term = $this->resolveTermForDate($eventDate) ?? $schedule->academicTerm;

            $exception = ScheduleException::updateOrCreate(
                [
                    'schedule_id' => $schedule->id,
                    'event_date'  => $eventDate,
                    'event_type'  => 'cancellation',
                ],
                [
                    'academic_term_id' => $term->id,
                    'room_id'          => $schedule->room_id,
                    'start_time'       => $schedule->start_time,
                    'end_time'         => $schedule->end_time,
                    'subject_code'     => $schedule->subject_code,
                    'subject_title'    => $schedule->subject_title,
                    'section'          => $schedule->section,
                    'instructor_name'  => $schedule->instructor_name,
                    'reason'           => $reason,
                    'status'           => 'cancelled',
                    'auto_cancel_at'   => null,
                    'claimed_at'       => null,
                    'created_by'       => $userId,
                    'updated_by'       => $userId,
                ]
            );

            $this->markScheduleUsage($schedule, $eventDate, 'cancelled', $userId);

            return $exception;
        });
    }

    public function changeRoom(array $data, int $userId): ScheduleException
    {
        return DB::transaction(function () use ($data, $userId) {
            $eventDate = Carbon::parse($data['event_date'])->toDateString();
            $reason = $data['reason'] ?? null;
            $newRoomId = (int) $data['room_id'];

            if (! empty($data['exception_id'])) {
                $exception = ScheduleException::findOrFail($data['exception_id']);
                $exception->update([
                    'room_id'        => $newRoomId,
                    'reason'         => $reason ?: $exception->reason,
                    'status'         => 'pending',
                    'auto_cancel_at' => $this->autoCancelAt($eventDate, $this->timeValue($exception->start_time)),
                    'claimed_at'     => null,
                    'updated_by'     => $userId,
                ]);

                return $exception;
            }

            if (empty($data['schedule_id'])) {
                throw ValidationException::withMessages([
                    'schedule_id' => 'A schedule or exception is required.',
                ]);
            }

            $schedule = Schedule::findOrFail($data['schedule_id']);
            $term = $this->resolveTermForDate($eventDate) ?? $schedule->academicTerm;

            return ScheduleException::updateOrCreate(
                [
                    'schedule_id' => $schedule->id,
                    'event_date'  => $eventDate,
                    'event_type'  => 'room_change',
                ],
                [
                    'academic_term_id' => $term->id,
                    'room_id'          => $newRoomId,
                    'start_time'       => $schedule->start_time,
                    'end_time'         => $schedule->end_time,
                    'subject_code'     => $schedule->subject_code,
                    'subject_title'    => $schedule->subject_title,
                    'section'          => $schedule->section,
                    'instructor_name'  => $schedule->instructor_name,
                    'reason'           => $reason,
                    'status'           => 'pending',
                    'auto_cancel_at'   => $this->autoCancelAt($eventDate, $this->timeValue($schedule->start_time)),
                    'claimed_at'       => null,
                    'created_by'       => $userId,
                    'updated_by'       => $userId,
                ]
            );
        });
    }

    public function markStarted(array $data, int $userId): RoomUsageLog
    {
        return DB::transaction(function () use ($data, $userId) {
            $eventDate = Carbon::parse($data['event_date'])->toDateString();

            if (! empty($data['exception_id'])) {
                $exception = ScheduleException::findOrFail($data['exception_id']);

                $exceptionStatus = $exception->status instanceof \BackedEnum ? $exception->status->value : (string) $exception->status;

                if (in_array($exceptionStatus, ['cancelled', 'auto_cancelled'], true)) {
                    throw ValidationException::withMessages([
                        'exception_id' => 'Cancelled or auto-cancelled classes cannot be marked as started.',
                    ]);
                }

                $exception->update([
                    'status'     => 'ongoing',
                    'claimed_at' => now(),
                    'updated_by' => $userId,
                ]);

                return $this->markExceptionUsage($exception, 'occupied', $userId, actualStart: now());
            }

            if (empty($data['schedule_id'])) {
                throw ValidationException::withMessages([
                    'schedule_id' => 'A schedule or exception is required.',
                ]);
            }

            $schedule = Schedule::findOrFail($data['schedule_id']);

            return $this->markScheduleUsage($schedule, $eventDate, 'occupied', $userId, actualStart: now());
        });
    }

    public function markCompleted(array $data, int $userId): RoomUsageLog
    {
        return DB::transaction(function () use ($data, $userId) {
            $eventDate = Carbon::parse($data['event_date'])->toDateString();

            if (! empty($data['exception_id'])) {
                $exception = ScheduleException::findOrFail($data['exception_id']);
                $exception->update([
                    'status'     => 'completed',
                    'updated_by' => $userId,
                ]);

                return $this->markExceptionUsage($exception, 'completed', $userId, actualEnd: now());
            }

            if (empty($data['schedule_id'])) {
                throw ValidationException::withMessages([
                    'schedule_id' => 'A schedule or exception is required.',
                ]);
            }

            $schedule = Schedule::findOrFail($data['schedule_id']);

            return $this->markScheduleUsage($schedule, $eventDate, 'completed', $userId, actualEnd: now());
        });
    }

    public function markScheduleUsage(
        Schedule $schedule,
        string $eventDate,
        string $status,
        int $userId,
        ?Carbon $actualStart = null,
        ?Carbon $actualEnd = null,
    ): RoomUsageLog {
        $log = RoomUsageLog::query()
            ->whereDate('usage_date', $eventDate)
            ->where('source', 'schedule')
            ->where('schedule_id', $schedule->id)
            ->first() ?? new RoomUsageLog([
                'usage_date'     => $eventDate,
                'source'         => 'schedule',
                'schedule_id'    => $schedule->id,
                'room_id'        => $schedule->room_id,
                'subject_code'   => $schedule->subject_code,
                'subject_title'  => $schedule->subject_title,
                'section'        => $schedule->section,
                'instructor_name'=> $schedule->instructor_name,
                'expected_start' => $this->timeValue($schedule->start_time),
                'expected_end'   => $this->timeValue($schedule->end_time),
            ]);

        return $this->applyUsageStatus($log, $status, $userId, $actualStart, $actualEnd);
    }

    public function markExceptionUsage(
        ScheduleException $exception,
        string $status,
        int $userId,
        ?Carbon $actualStart = null,
        ?Carbon $actualEnd = null,
    ): RoomUsageLog {
        $log = RoomUsageLog::query()
            ->whereDate('usage_date', $exception->event_date)
            ->where('source', 'schedule_exception')
            ->where('schedule_exception_id', $exception->id)
            ->first() ?? new RoomUsageLog([
                'usage_date'             => $exception->event_date,
                'source'                 => 'schedule_exception',
                'schedule_id'            => $exception->schedule_id,
                'schedule_exception_id'  => $exception->id,
                'room_id'                => $exception->room_id,
                'subject_code'           => $exception->subject_code ?: ($exception->schedule?->subject_code ?? ''),
                'subject_title'          => $exception->subject_title ?: ($exception->schedule?->subject_title ?? ''),
                'section'                => $exception->section ?: ($exception->schedule?->section ?? ''),
                'instructor_name'        => $exception->instructor_name ?: $exception->schedule?->instructor_name,
                'expected_start'         => $this->timeValue($exception->start_time ?: $exception->schedule?->start_time),
                'expected_end'           => $this->timeValue($exception->end_time ?: $exception->schedule?->end_time),
            ]);

        return $this->applyUsageStatus($log, $status, $userId, $actualStart, $actualEnd);
    }

    private function applyUsageStatus(
        RoomUsageLog $log,
        string $status,
        int $userId,
        ?Carbon $actualStart = null,
        ?Carbon $actualEnd = null,
    ): RoomUsageLog {
        $log->status = $status;
        $log->recorded_by = $userId;

        if ($actualStart) {
            $log->actual_start = $actualStart;
        }

        if ($actualEnd) {
            $log->actual_end = $actualEnd;

            if (! $log->actual_start) {
                $log->actual_start = $actualEnd;
            }
        }

        if (in_array($status, ['cancelled', 'auto_cancelled'], true)) {
            $log->actual_end = $log->actual_end ?: now();
        }

        $log->save();

        return $log;
    }

    private function resolveTermForDate(string $date): ?AcademicTerm
    {
        return AcademicTerm::query()
            ->where(function ($query) use ($date) {
                $query->whereNull('starts_on')
                    ->orWhereDate('starts_on', '<=', $date);
            })
            ->where(function ($query) use ($date) {
                $query->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $date);
            })
            ->orderByDesc('is_current')
            ->orderByDesc('is_active')
            ->latest('id')
            ->first()
            ?? AcademicTerm::current()->first();
    }

    private function autoCancelAt(string $eventDate, string $startTime): Carbon
    {
        return Carbon::parse("{$eventDate} {$startTime}")->addHour();
    }

    private function timeValue(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('H:i');
        }

        return substr((string) $value, 0, 5);
    }
}
