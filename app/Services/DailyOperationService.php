<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\RoomOverride;
use App\Models\RoomUsageLog;
use App\Services\Support\ValueNormalizer;
use App\Models\Schedule;
use App\Models\ScheduleException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyOperationService
{
    public function __construct(
        private readonly AcademicTermService $academicTermService,
        private readonly RoomUsageLogService $roomUsageLogService,
        private readonly ValueNormalizer $normalizer,
    ) {}

    public function claimGraceMinutes(): int
    {
        return max(1, (int) config('daily_operations.claim_grace_minutes', 60));
    }

    public function requestClass(array $data, int $userId): ScheduleException
    {
        $eventDate = Carbon::parse($data['event_date'])->toDateString();
        $term = $this->academicTermService->resolveForDate($eventDate);

        if (! $term) {
            throw ValidationException::withMessages([
                'event_date' => 'No academic term is available for the selected date.',
            ]);
        }

        $this->assertRoomSlotAvailable(
            eventDate: $eventDate,
            term: $term,
            roomId: (int) $data['room_id'],
            startTime: $data['start_time'],
            endTime: $data['end_time'],
        );

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

                $this->roomUsageLogService->markExceptionUsage($exception, 'cancelled', $userId);

                return $exception;
            }

            if (empty($data['schedule_id'])) {
                throw ValidationException::withMessages([
                    'schedule_id' => 'A schedule or exception is required.',
                ]);
            }

            $schedule = Schedule::findOrFail($data['schedule_id']);
            $term = $this->academicTermService->resolveForDate($eventDate) ?? $schedule->academicTerm;

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

            $this->roomUsageLogService->markScheduleUsage($schedule, $eventDate, 'cancelled', $userId);

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
                $exception = ScheduleException::with('schedule')->findOrFail($data['exception_id']);
                $term = $this->academicTermService->resolveForDate($eventDate) ?? $exception->academicTerm;

                if (! $term) {
                    throw ValidationException::withMessages([
                        'event_date' => 'No academic term is available for the selected date.',
                    ]);
                }

                $originalStartTime = $this->normalizer->timeString($exception->start_time);
                $originalEndTime = $this->normalizer->timeString($exception->end_time);
                $effectiveStartTime = $this->effectiveRoomChangeStartTime(
                    eventDate: $eventDate,
                    term: $term,
                    roomId: $newRoomId,
                    startTime: $originalStartTime,
                    endTime: $originalEndTime,
                    ignoreScheduleId: $exception->schedule_id,
                );

                $this->assertRoomSlotAvailable(
                    eventDate: $eventDate,
                    term: $term,
                    roomId: $newRoomId,
                    startTime: $effectiveStartTime,
                    endTime: $originalEndTime,
                    ignoreScheduleId: $exception->schedule_id,
                    ignoreExceptionId: $exception->id,
                );

                $exception->update([
                    'room_id'        => $newRoomId,
                    'start_time'     => $effectiveStartTime,
                    'end_time'       => $originalEndTime,
                    'reason'         => $reason ?: $exception->reason,
                    'status'         => 'pending',
                    'auto_cancel_at' => $this->autoCancelAt($eventDate, $effectiveStartTime),
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
            $term = $this->academicTermService->resolveForDate($eventDate) ?? $schedule->academicTerm;

            if (! $term) {
                throw ValidationException::withMessages([
                    'event_date' => 'No academic term is available for the selected date.',
                ]);
            }

            $originalStartTime = $this->normalizer->timeString($schedule->start_time);
            $originalEndTime = $this->normalizer->timeString($schedule->end_time);
            $effectiveStartTime = $this->effectiveRoomChangeStartTime(
                eventDate: $eventDate,
                term: $term,
                roomId: $newRoomId,
                startTime: $originalStartTime,
                endTime: $originalEndTime,
                ignoreScheduleId: $schedule->id,
            );

            $this->assertRoomSlotAvailable(
                eventDate: $eventDate,
                term: $term,
                roomId: $newRoomId,
                startTime: $effectiveStartTime,
                endTime: $originalEndTime,
                ignoreScheduleId: $schedule->id,
            );

            return ScheduleException::updateOrCreate(
                [
                    'schedule_id' => $schedule->id,
                    'event_date'  => $eventDate,
                    'event_type'  => 'room_change',
                ],
                [
                    'academic_term_id' => $term->id,
                    'room_id'          => $newRoomId,
                    'start_time'       => $effectiveStartTime,
                    'end_time'         => $originalEndTime,
                    'subject_code'     => $schedule->subject_code,
                    'subject_title'    => $schedule->subject_title,
                    'section'          => $schedule->section,
                    'instructor_name'  => $schedule->instructor_name,
                    'reason'           => $reason,
                    'status'           => 'pending',
                    'auto_cancel_at'   => $this->autoCancelAt($eventDate, $effectiveStartTime),
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

                $eventDate = $this->normalizer->dateString($exception->event_date);
                $term = $this->academicTermService->resolveForDate($eventDate) ?? $exception->academicTerm;

                if ($term) {
                    $this->assertRoomSlotAvailable(
                        eventDate: $eventDate,
                        term: $term,
                        roomId: $exception->room_id,
                        startTime: $this->normalizer->timeString($exception->start_time ?: $exception->schedule?->start_time),
                        endTime: $this->normalizer->timeString($exception->end_time ?: $exception->schedule?->end_time),
                        ignoreScheduleId: $exception->schedule_id,
                        ignoreExceptionId: $exception->id,
                    );
                }

                $exception->update([
                    'status'     => 'ongoing',
                    'claimed_at' => now(),
                    'updated_by' => $userId,
                ]);

                return $this->roomUsageLogService->markExceptionUsage($exception, 'occupied', $userId, actualStart: now());
            }

            if (empty($data['schedule_id'])) {
                throw ValidationException::withMessages([
                    'schedule_id' => 'A schedule or exception is required.',
                ]);
            }

            $schedule = Schedule::findOrFail($data['schedule_id']);
            $term = $this->academicTermService->resolveForDate($eventDate) ?? $schedule->academicTerm;

            if ($term) {
                $this->assertRoomSlotAvailable(
                    eventDate: $eventDate,
                    term: $term,
                    roomId: $schedule->room_id,
                    startTime: $this->normalizer->timeString($schedule->start_time),
                    endTime: $this->normalizer->timeString($schedule->end_time),
                    ignoreScheduleId: $schedule->id,
                );
            }

            return $this->roomUsageLogService->markScheduleUsage($schedule, $eventDate, 'occupied', $userId, actualStart: now());
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

                return $this->roomUsageLogService->markExceptionUsage($exception, 'completed', $userId, actualEnd: now());
            }

            if (empty($data['schedule_id'])) {
                throw ValidationException::withMessages([
                    'schedule_id' => 'A schedule or exception is required.',
                ]);
            }

            $schedule = Schedule::findOrFail($data['schedule_id']);

            return $this->roomUsageLogService->markScheduleUsage($schedule, $eventDate, 'completed', $userId, actualEnd: now());
        });
    }

    public function revertStarted(array $data, int $userId): RoomUsageLog
    {
        return DB::transaction(function () use ($data, $userId) {
            $eventDate = Carbon::parse($data['event_date'])->toDateString();

            if (! empty($data['exception_id'])) {
                $exception = ScheduleException::findOrFail($data['exception_id']);
                $usageLog = $this->roomUsageLogService->existingExceptionUsageLog($exception);

                $this->assertUsageLogStatus(
                    usageLog: $usageLog,
                    allowedStatuses: ['occupied'],
                    messageKey: 'exception_id',
                    message: 'Only started classes can have their start action reverted.',
                );

                $exception->update([
                    'status'     => 'pending',
                    'claimed_at' => null,
                    'updated_by' => $userId,
                ]);

                return $this->roomUsageLogService->resetToAwaiting($usageLog, $userId);
            }

            if (empty($data['schedule_id'])) {
                throw ValidationException::withMessages([
                    'schedule_id' => 'A schedule or exception is required.',
                ]);
            }

            $schedule = Schedule::findOrFail($data['schedule_id']);
            $usageLog = $this->roomUsageLogService->existingScheduleUsageLog($schedule, $eventDate);

            $this->assertUsageLogStatus(
                usageLog: $usageLog,
                allowedStatuses: ['occupied'],
                messageKey: 'schedule_id',
                message: 'Only started classes can have their start action reverted.',
            );

            return $this->roomUsageLogService->resetToAwaiting($usageLog, $userId);
        });
    }

    public function revertCompleted(array $data, int $userId): RoomUsageLog
    {
        return DB::transaction(function () use ($data, $userId) {
            $eventDate = Carbon::parse($data['event_date'])->toDateString();

            if (! empty($data['exception_id'])) {
                $exception = ScheduleException::findOrFail($data['exception_id']);
                $usageLog = $this->roomUsageLogService->existingExceptionUsageLog($exception);

                $this->assertUsageLogStatus(
                    usageLog: $usageLog,
                    allowedStatuses: ['completed'],
                    messageKey: 'exception_id',
                    message: 'Only completed classes can have their completion reverted.',
                );

                $exception->update([
                    'status'     => 'ongoing',
                    'claimed_at' => $exception->claimed_at ?: ($usageLog->actual_start ?: now()),
                    'updated_by' => $userId,
                ]);

                return $this->roomUsageLogService->reopenCompleted($usageLog, $userId);
            }

            if (empty($data['schedule_id'])) {
                throw ValidationException::withMessages([
                    'schedule_id' => 'A schedule or exception is required.',
                ]);
            }

            $schedule = Schedule::findOrFail($data['schedule_id']);
            $usageLog = $this->roomUsageLogService->existingScheduleUsageLog($schedule, $eventDate);

            $this->assertUsageLogStatus(
                usageLog: $usageLog,
                allowedStatuses: ['completed'],
                messageKey: 'schedule_id',
                message: 'Only completed classes can have their completion reverted.',
            );

            return $this->roomUsageLogService->reopenCompleted($usageLog, $userId);
        });
    }

    public function revertCancellation(array $data, int $userId): RoomUsageLog
    {
        return DB::transaction(function () use ($data, $userId) {
            $eventDate = Carbon::parse($data['event_date'])->toDateString();

            if (! empty($data['exception_id'])) {
                $exception = ScheduleException::with('schedule')->findOrFail($data['exception_id']);
                $eventType = $this->normalizer->enumString($exception->event_type);
                $exceptionStatus = $this->normalizer->enumString($exception->status);

                if ($eventType === 'cancellation') {
                    $schedule = $exception->schedule;

                    if (! $schedule) {
                        throw ValidationException::withMessages([
                            'exception_id' => 'The cancelled schedule record is no longer available.',
                        ]);
                    }

                    $this->assertScheduleCanBeRestored($schedule, $eventDate, $exception->id);

                    $usageLog = $this->roomUsageLogService->existingScheduleUsageLog($schedule, $eventDate)
                        ?? $this->roomUsageLogService->markScheduleUsage($schedule, $eventDate, 'reserved', $userId);

                    $usageLog = $this->roomUsageLogService->resetToAwaiting($usageLog, $userId);
                    $exception->delete();

                    return $usageLog;
                }

                if ($exceptionStatus !== 'cancelled') {
                    throw ValidationException::withMessages([
                        'exception_id' => 'Only manually cancelled classes can be restored.',
                    ]);
                }

                $this->assertExceptionCanBeRestored($exception);

                $exception->update([
                    'status'         => 'pending',
                    'claimed_at'     => null,
                    'auto_cancel_at' => $this->autoCancelAt(
                        $this->normalizer->dateString($exception->event_date),
                        $this->normalizer->timeString($exception->start_time ?: $exception->schedule?->start_time),
                    ),
                    'updated_by'     => $userId,
                ]);

                $usageLog = $this->roomUsageLogService->existingExceptionUsageLog($exception)
                    ?? $this->roomUsageLogService->markExceptionUsage($exception, 'reserved', $userId);

                return $this->roomUsageLogService->resetToAwaiting($usageLog, $userId);
            }

            if (empty($data['schedule_id'])) {
                throw ValidationException::withMessages([
                    'schedule_id' => 'A schedule or exception is required.',
                ]);
            }

            $schedule = Schedule::findOrFail($data['schedule_id']);
            $cancellationException = ScheduleException::query()
                ->where('schedule_id', $schedule->id)
                ->whereDate('event_date', $eventDate)
                ->where('event_type', 'cancellation')
                ->first();

            $usageLog = $this->roomUsageLogService->existingScheduleUsageLog($schedule, $eventDate);

            if (! $cancellationException && $this->normalizer->enumString($usageLog?->status) !== 'cancelled') {
                throw ValidationException::withMessages([
                    'schedule_id' => 'No cancelled class record was found to restore.',
                ]);
            }

            $this->assertScheduleCanBeRestored($schedule, $eventDate, $cancellationException?->id);

            $usageLog ??= $this->roomUsageLogService->markScheduleUsage($schedule, $eventDate, 'reserved', $userId);
            $usageLog = $this->roomUsageLogService->resetToAwaiting($usageLog, $userId);
            $cancellationException?->delete();

            return $usageLog;
        });
    }

    /**
     * @param  array<int, string>  $allowedStatuses
     */
    private function assertUsageLogStatus(
        ?RoomUsageLog $usageLog,
        array $allowedStatuses,
        string $messageKey,
        string $message,
    ): void {
        if (! $usageLog || ! in_array($this->normalizer->enumString($usageLog->status), $allowedStatuses, true)) {
            throw ValidationException::withMessages([
                $messageKey => $message,
                'usage_log_id' => 'The matching room usage log could not be found in the expected state.',
            ]);
        }
    }

    private function assertScheduleCanBeRestored(
        Schedule $schedule,
        string $eventDate,
        ?int $ignoreExceptionId = null,
    ): void {
        $term = $this->academicTermService->resolveForDate($eventDate) ?? $schedule->academicTerm;

        if (! $term) {
            throw ValidationException::withMessages([
                'event_date' => 'No academic term is available for the selected date.',
            ]);
        }

        $this->assertRoomSlotAvailable(
            eventDate: $eventDate,
            term: $term,
            roomId: $schedule->room_id,
            startTime: $this->normalizer->timeString($schedule->start_time),
            endTime: $this->normalizer->timeString($schedule->end_time),
            ignoreScheduleId: $schedule->id,
            ignoreExceptionId: $ignoreExceptionId,
        );
    }

    private function assertExceptionCanBeRestored(ScheduleException $exception): void
    {
        $eventDate = $this->normalizer->dateString($exception->event_date);
        $term = $this->academicTermService->resolveForDate($eventDate) ?? $exception->academicTerm;

        if (! $term) {
            throw ValidationException::withMessages([
                'event_date' => 'No academic term is available for the selected date.',
            ]);
        }

        $this->assertRoomSlotAvailable(
            eventDate: $eventDate,
            term: $term,
            roomId: $exception->room_id,
            startTime: $this->normalizer->timeString($exception->start_time ?: $exception->schedule?->start_time),
            endTime: $this->normalizer->timeString($exception->end_time ?: $exception->schedule?->end_time),
            ignoreScheduleId: $exception->schedule_id,
            ignoreExceptionId: $exception->id,
        );
    }



    /**
     * Change-room actions do not expose a custom start time in the UI because
     * they normally reuse the selected class window. When the target room is
     * blocked only by an unclaimed regular class, the request should claim the
     * remaining reclaimable time instead of failing against the protected claim
     * window. This does not create an unclaimed usage log and does not edit the
     * weekly schedule template.
     */
    private function effectiveRoomChangeStartTime(
        string $eventDate,
        AcademicTerm $term,
        int $roomId,
        string $startTime,
        string $endTime,
        ?int $ignoreScheduleId = null,
    ): string {
        $startTime = $this->normalizer->timeString($startTime);
        $endTime = $this->normalizer->timeString($endTime);
        $dayOfWeek = Carbon::parse($eventDate)->dayOfWeekIso;
        $replacedScheduleIds = $this->replacedScheduleIdsForDate($eventDate, $term->id);
        $effectiveStartTime = $startTime;

        $scheduleConflicts = Schedule::query()
            ->active()
            ->where('academic_term_id', $term->id)
            ->where('room_id', $roomId)
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->when($ignoreScheduleId, fn ($query) => $query->whereKeyNot($ignoreScheduleId))
            ->when($replacedScheduleIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $replacedScheduleIds))
            ->orderBy('start_time')
            ->get();

        foreach ($scheduleConflicts as $scheduleConflict) {
            [$conflictStart, $conflictEnd, $windowState] = $this->effectiveScheduleWindow($scheduleConflict, $eventDate);

            if ($windowState !== 'unclaimed') {
                continue;
            }

            if ($conflictStart > $effectiveStartTime || $conflictEnd <= $effectiveStartTime) {
                continue;
            }

            $effectiveStartTime = $conflictEnd;
        }

        if ($effectiveStartTime >= $endTime) {
            throw ValidationException::withMessages([
                'room_id' => 'The selected room is only reclaimable after this class has already ended. Choose another room or time.',
                'start_time' => 'The remaining reclaimable time must start before the class end time.',
                'end_time' => 'The remaining reclaimable time must start before the class end time.',
            ]);
        }

        return $effectiveStartTime;
    }

    private function assertRoomSlotAvailable(
        string $eventDate,
        AcademicTerm $term,
        int $roomId,
        string $startTime,
        string $endTime,
        ?int $ignoreScheduleId = null,
        ?int $ignoreExceptionId = null,
    ): void {
        $startTime = $this->normalizer->timeString($startTime);
        $endTime = $this->normalizer->timeString($endTime);
        $dayOfWeek = Carbon::parse($eventDate)->dayOfWeekIso;

        if ($startTime >= $endTime) {
            throw ValidationException::withMessages([
                'end_time' => 'End time must be after start time.',
            ]);
        }

        $this->assertNoRoomOverrideConflict(
            eventDate: $eventDate,
            roomId: $roomId,
            startTime: $startTime,
            endTime: $endTime,
        );

        $replacedScheduleIds = $this->replacedScheduleIdsForDate($eventDate, $term->id);

        $scheduleConflicts = Schedule::query()
            ->active()
            ->with('room:id,code,name')
            ->where('academic_term_id', $term->id)
            ->where('room_id', $roomId)
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->when($ignoreScheduleId, fn ($query) => $query->whereKeyNot($ignoreScheduleId))
            ->when($replacedScheduleIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $replacedScheduleIds))
            ->orderBy('start_time')
            ->get();

        foreach ($scheduleConflicts as $scheduleConflict) {
            [$effectiveStart, $effectiveEnd, $windowState] = $this->effectiveScheduleWindow($scheduleConflict, $eventDate);

            if (! $this->timeWindowsOverlap($startTime, $endTime, $effectiveStart, $effectiveEnd)) {
                continue;
            }

            $conflictType = match ($windowState) {
                'completed' => 'completed regular class',
                'unclaimed' => 'regular class claim window',
                default => 'regular class',
            };

            $this->throwSlotConflict(
                roomLabel: $this->roomLabel($roomId),
                conflictType: $conflictType,
                subjectCode: $scheduleConflict->subject_code ?? 'Class',
                startTime: $effectiveStart,
                endTime: $effectiveEnd,
            );
        }

        $exceptionConflicts = ScheduleException::query()
            ->with(['room:id,code,name', 'schedule:id,subject_code'])
            ->whereDate('event_date', $eventDate)
            ->where('academic_term_id', $term->id)
            ->where('room_id', $roomId)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->where(function ($query) {
                $query->whereNotIn('status', ['cancelled', 'auto_cancelled'])
                    ->where('event_type', '!=', 'cancellation');
            })
            ->when($ignoreExceptionId, fn ($query) => $query->whereKeyNot($ignoreExceptionId))
            ->orderBy('start_time')
            ->get();

        foreach ($exceptionConflicts as $exceptionConflict) {
            [$effectiveStart, $effectiveEnd, $windowState] = $this->effectiveExceptionWindow($exceptionConflict);

            if (! $this->timeWindowsOverlap($startTime, $endTime, $effectiveStart, $effectiveEnd)) {
                continue;
            }

            $this->throwSlotConflict(
                roomLabel: $this->roomLabel($roomId),
                conflictType: ($windowState === 'completed' ? 'completed ' : '') . str_replace('_', ' ', $this->normalizer->enumString($exceptionConflict->event_type)),
                subjectCode: $exceptionConflict->subject_code ?: ($exceptionConflict->schedule?->subject_code ?? 'Class'),
                startTime: $effectiveStart,
                endTime: $effectiveEnd,
            );
        }
    }

    /**
     * Returns the conflict window that should still block same-day class requests.
     *
     * Completed classes only block until actual_end. Unclaimed regular classes
     * only block until their claim deadline; after that deadline the remaining
     * time can be reclaimed by a special/makeup/change-room operation without
     * writing an "unclaimed" usage-log row for the original schedule.
     *
     * @return array{0:string,1:string,2:string|null}
     */
    private function effectiveScheduleWindow(Schedule $schedule, string $eventDate): array
    {
        $startTime = $this->normalizer->timeString($schedule->start_time);
        $endTime = $this->normalizer->timeString($schedule->end_time);

        $usageLog = RoomUsageLog::query()
            ->whereDate('usage_date', $eventDate)
            ->where('source', 'schedule')
            ->where('schedule_id', $schedule->id)
            ->first();

        [$effectiveStart, $effectiveEnd, $windowState] = $this->trimWindowByCompletedUsageLog($startTime, $endTime, $usageLog);

        if ($windowState !== null) {
            return [$effectiveStart, $effectiveEnd, $windowState];
        }

        if ($this->scheduleIsUnclaimed($eventDate, $startTime, $endTime, $usageLog)) {
            return [$startTime, $this->claimDeadlineTime($eventDate, $startTime), 'unclaimed'];
        }

        return [$startTime, $endTime, null];
    }

    /**
     * @return array{0:string,1:string,2:string|null}
     */
    private function effectiveExceptionWindow(ScheduleException $exception): array
    {
        $startTime = $this->normalizer->timeString($exception->start_time ?: $exception->schedule?->start_time);
        $endTime = $this->normalizer->timeString($exception->end_time ?: $exception->schedule?->end_time);

        $usageLog = RoomUsageLog::query()
            ->whereDate('usage_date', $exception->event_date)
            ->where('source', 'schedule_exception')
            ->where('schedule_exception_id', $exception->id)
            ->first();

        return $this->trimWindowByCompletedUsageLog($startTime, $endTime, $usageLog);
    }

    /**
     * @return array{0:string,1:string,2:string|null}
     */
    private function trimWindowByCompletedUsageLog(string $startTime, string $endTime, ?RoomUsageLog $usageLog): array
    {
        if (! $usageLog) {
            return [$startTime, $endTime, null];
        }

        $status = $this->normalizer->enumString($usageLog->status);

        if (in_array($status, ['cancelled', 'auto_cancelled'], true)) {
            return [$startTime, $startTime, $status];
        }

        if ($status !== 'completed' || ! $usageLog->actual_end) {
            return [$startTime, $endTime, null];
        }

        $actualEnd = $this->normalizer->timeString($usageLog->actual_end);

        if ($actualEnd <= $startTime) {
            return [$startTime, $startTime, 'completed'];
        }

        if ($actualEnd < $endTime) {
            return [$startTime, $actualEnd, 'completed'];
        }

        return [$startTime, $endTime, null];
    }

    private function scheduleIsUnclaimed(string $eventDate, string $startTime, string $endTime, ?RoomUsageLog $usageLog): bool
    {
        $claimDeadline = $this->claimDeadlineAt($eventDate, $startTime);
        $scheduleEnd = Carbon::parse("{$eventDate} {$endTime}");

        if ($claimDeadline->greaterThanOrEqualTo($scheduleEnd)) {
            return false;
        }

        if (now()->lessThan($claimDeadline)) {
            return false;
        }

        $status = $this->normalizer->enumString($usageLog?->status);

        if ($status !== '' && ! in_array($status, ['reserved'], true)) {
            return false;
        }

        return ! $usageLog || (! $usageLog->actual_start && ! $usageLog->actual_end);
    }

    private function claimDeadlineAt(string $eventDate, string $startTime): Carbon
    {
        return Carbon::parse("{$eventDate} {$startTime}")->addMinutes($this->claimGraceMinutes());
    }

    private function claimDeadlineTime(string $eventDate, string $startTime): string
    {
        return $this->claimDeadlineAt($eventDate, $startTime)->format('H:i');
    }

    private function timeWindowsOverlap(string $firstStart, string $firstEnd, string $secondStart, string $secondEnd): bool
    {
        return $firstStart < $secondEnd && $firstEnd > $secondStart;
    }

    private function assertNoRoomOverrideConflict(
        string $eventDate,
        int $roomId,
        string $startTime,
        string $endTime,
    ): void {
        $requestedStart = Carbon::parse("{$eventDate} {$startTime}");
        $requestedEnd = Carbon::parse("{$eventDate} {$endTime}");

        $overrideConflict = RoomOverride::query()
            ->with('room:id,code,name')
            ->where('room_id', $roomId)
            ->where('is_active', true)
            ->where('starts_at', '<', $requestedEnd)
            ->where(function ($query) use ($requestedStart) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', $requestedStart);
            })
            ->orderBy('starts_at')
            ->first();

        if (! $overrideConflict) {
            return;
        }

        $status = $this->normalizer->enumString($overrideConflict->status);
        $label = ucfirst(str_replace('_', ' ', $status));
        $startsAt = Carbon::parse($overrideConflict->starts_at)->format('g:i A');
        $endsAt = $overrideConflict->ends_at
            ? Carbon::parse($overrideConflict->ends_at)->format('g:i A')
            : 'until cleared';
        $reason = trim((string) $overrideConflict->reason);
        $reasonText = $reason !== '' ? " Reason: {$reason}" : '';

        throw ValidationException::withMessages([
            'room_id' => "{$this->roomLabel($roomId)} has an active {$label} override from {$startsAt} to {$endsAt}.{$reasonText} Clear or end the room override first, or choose another room/time.",
            'start_time' => 'The selected time overlaps an active room override.',
            'end_time' => 'The selected time overlaps an active room override.',
        ]);
    }

    private function replacedScheduleIdsForDate(string $eventDate, int $termId): \Illuminate\Support\Collection
    {
        return ScheduleException::query()
            ->whereDate('event_date', $eventDate)
            ->where('academic_term_id', $termId)
            ->whereNotNull('schedule_id')
            ->where(function ($query) {
                $query->where('event_type', 'cancellation')
                    ->orWhere(function ($query) {
                        $query->where('event_type', 'room_change')
                            ->whereNotIn('status', ['cancelled', 'auto_cancelled']);
                    });
            })
            ->pluck('schedule_id')
            ->filter()
            ->unique()
            ->values();
    }

    private function throwSlotConflict(
        string $roomLabel,
        string $conflictType,
        string $subjectCode,
        string $startTime,
        string $endTime,
    ): never {
        throw ValidationException::withMessages([
            'room_id' => "{$roomLabel} already has an overlapping {$conflictType}: {$subjectCode} ({$startTime}–{$endTime}). Cancel the existing class first or choose another room/time.",
            'start_time' => 'The selected time overlaps an existing class or exception.',
            'end_time' => 'The selected time overlaps an existing class or exception.',
        ]);
    }

    private function roomLabel(int $roomId): string
    {
        $room = \App\Models\Room::query()->select('code', 'name')->find($roomId);

        if (! $room) {
            return "Room #{$roomId}";
        }

        return trim("{$room->code} {$room->name}");
    }

    private function autoCancelAt(string $eventDate, string $startTime): Carbon
    {
        return $this->claimDeadlineAt($eventDate, $startTime);
    }

}
