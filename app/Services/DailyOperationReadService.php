<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomOverride;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Services\Support\ValueNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DailyOperationReadService
{
    public function __construct(
        private readonly AcademicTermService $academicTermService,
        private readonly DailyOperationService $dailyOperationService,
        private readonly RoomUsageLogService $roomUsageLogService,
        private readonly ValueNormalizer $normalizer,
    ) {}

    /**
     * Returns the same read payload used by Admin Daily Operations and kiosk APIs.
     * Keep all Daily Operations visual reads here so controllers and APIs do not
     * duplicate the schedule / exception / override priority chain.
     */
    public function payloadForDate(?string $selectedDateInput = null): array
    {
        $selectedDateInput ??= now()->toDateString();

        try {
            $date = Carbon::parse($selectedDateInput)->startOfDay();
        } catch (\Throwable) {
            $date = now()->startOfDay();
        }

        $selectedDate = $date->toDateString();
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();
        $operationTerm = $this->academicTermService->resolveForDate($date);

        $roomsBaseQuery = Room::query()
            ->select('id', 'code', 'name', 'room_type', 'floor', 'capacity', 'display_order', 'is_active')
            ->orderBy('display_order')
            ->orderBy('code');

        $roomOverrides = RoomOverride::query()
            ->enabled()
            ->with('room:id,code,name,room_type,floor')
            ->where('starts_at', '<=', $dayEnd)
            ->where(function ($query) use ($dayStart) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $dayStart);
            })
            ->orderBy('starts_at')
            ->get();

        $regularSchedules = collect();
        $exceptions = collect();

        if ($operationTerm) {
            $exceptions = $this->scheduleExceptionsForDate($selectedDate, $operationTerm->id);

            $scheduleIdsWithReplacingExceptions = $exceptions
                ->filter(function (ScheduleException $exception) {
                    if (! $exception->schedule_id) {
                        return false;
                    }

                    $eventType = $this->normalizer->enumString($exception->event_type);
                    $status = $this->normalizer->enumString($exception->status);

                    return in_array($eventType, ['cancellation', 'room_change'], true)
                        && $status !== 'auto_cancelled';
                })
                ->pluck('schedule_id')
                ->unique()
                ->values();

            $regularSchedules = Schedule::query()
                ->active()
                ->with('room:id,code,name,room_type,floor')
                ->where('academic_term_id', $operationTerm->id)
                ->where('day_of_week', $date->dayOfWeekIso)
                ->when($scheduleIdsWithReplacingExceptions->isNotEmpty(), function ($query) use ($scheduleIdsWithReplacingExceptions) {
                    $query->whereNotIn('id', $scheduleIdsWithReplacingExceptions);
                })
                ->orderBy('start_time')
                ->get();

            if ($exceptions->isEmpty()) {
                $baselineScheduleIds = $regularSchedules->pluck('id')->filter()->values();

                $exceptions = ScheduleException::query()
                    ->with([
                        'room:id,code,name,room_type,floor',
                        'schedule:id,room_id,subject_code,subject_title,section,instructor_name,start_time,end_time',
                        'schedule.room:id,code,name,room_type,floor',
                    ])
                    ->whereDate('event_date', $selectedDate)
                    ->where(function ($query) use ($baselineScheduleIds) {
                        $query->whereNull('schedule_id');

                        if ($baselineScheduleIds->isNotEmpty()) {
                            $query->orWhereIn('schedule_id', $baselineScheduleIds);
                        }
                    })
                    ->orderBy('start_time')
                    ->get();
            }
        }

        $usageLogsBySlot = $this->roomUsageLogService->logsBySlot($selectedDate);

        $usedRoomIds = $regularSchedules
            ->pluck('room_id')
            ->merge($exceptions->pluck('room_id'))
            ->merge($roomOverrides->pluck('room_id'))
            ->filter()
            ->unique()
            ->values();

        $rooms = $roomsBaseQuery
            ->where(function ($query) use ($usedRoomIds) {
                $query->where('is_active', true);

                if ($usedRoomIds->isNotEmpty()) {
                    $query->orWhereIn('id', $usedRoomIds);
                }
            })
            ->get()
            ->map(fn (Room $room) => [
                'id'            => $room->id,
                'code'          => $room->code,
                'name'          => $room->name,
                'type'          => $this->normalizer->enumValue($room->room_type),
                'floor'         => $room->floor,
                'capacity'      => $room->capacity,
                'display_order' => $room->display_order,
                'is_active'     => $room->is_active,
            ])
            ->values();

        $regularItems = $regularSchedules->map(function (Schedule $schedule) use ($selectedDate, $usageLogsBySlot) {
            $usageLog = $usageLogsBySlot->get("schedule-{$schedule->id}");

            return $this->roomUsageLogService->applyStatusToItem([
                'id'                 => "schedule-{$schedule->id}",
                'schedule_id'        => $schedule->id,
                'exception_id'       => null,
                'usage_log_id'       => $usageLog?->id,
                'override_id'        => null,
                'room_id'            => $schedule->room_id,
                'room_code'          => $schedule->room?->code,
                'original_room_id'   => null,
                'original_room_code' => null,
                'event_date'         => $selectedDate,
                'source'             => 'schedule',
                'event_type'         => 'regular',
                'status'             => 'scheduled',
                'subject_code'       => $schedule->subject_code ?? '',
                'subject_title'      => $schedule->subject_title ?? '',
                'section'            => $schedule->section ?? '',
                'instructor_name'    => $schedule->instructor_name,
                'start_time'         => $this->normalizer->time($schedule->start_time),
                'end_time'           => $this->normalizer->time($schedule->end_time),
                'reason'             => null,
            ], $usageLog);
        });

        $exceptionItems = $exceptions->map(function (ScheduleException $exception) use ($selectedDate, $usageLogsBySlot) {
            $schedule = $exception->schedule;
            $eventType = $this->normalizer->enumString($exception->event_type);
            $status = $this->normalizer->enumString($exception->status);
            $originalRoom = $schedule?->room;
            $usageLog = $usageLogsBySlot->get("exception-{$exception->id}");

            if ($eventType === 'cancellation') {
                $status = 'cancelled';
            }

            return $this->roomUsageLogService->applyStatusToItem([
                'id'                 => "exception-{$exception->id}",
                'schedule_id'        => $exception->schedule_id,
                'exception_id'       => $exception->id,
                'usage_log_id'       => $usageLog?->id,
                'override_id'        => null,
                'room_id'            => $exception->room_id,
                'room_code'          => $exception->room?->code,
                'original_room_id'   => $eventType === 'room_change' ? $schedule?->room_id : null,
                'original_room_code' => $eventType === 'room_change' ? $originalRoom?->code : null,
                'event_date'         => $selectedDate,
                'source'             => 'exception',
                'event_type'         => $eventType,
                'status'             => $status,
                'subject_code'       => $exception->subject_code ?: ($schedule?->subject_code ?? ''),
                'subject_title'      => $exception->subject_title ?: ($schedule?->subject_title ?? ''),
                'section'            => $exception->section ?: ($schedule?->section ?? ''),
                'instructor_name'    => $exception->instructor_name ?: $schedule?->instructor_name,
                'start_time'         => $this->normalizer->time($exception->start_time ?: $schedule?->start_time),
                'end_time'           => $this->normalizer->time($exception->end_time ?: $schedule?->end_time),
                'reason'             => $exception->reason,
                'claimed_at'         => $this->normalizer->dateTime($exception->claimed_at),
                'auto_cancel_at'     => $this->normalizer->dateTime($exception->auto_cancel_at),
            ], $usageLog);
        });

        $overrideItems = $roomOverrides->map(function (RoomOverride $override) use ($dayStart, $dayEnd, $selectedDate) {
            $status = $this->normalizer->enumString($override->status);

            $effectiveStart = $override->starts_at && $override->starts_at->greaterThan($dayStart)
                ? $override->starts_at
                : $dayStart;

            $effectiveEnd = $override->ends_at && $override->ends_at->lessThan($dayEnd)
                ? $override->ends_at
                : $dayEnd;

            $title = match ($status) {
                'maintenance' => 'Room Maintenance',
                'unavailable' => 'Room Unavailable',
                'reserved'    => 'Room Reserved',
                default       => 'Room Override',
            };

            return [
                'id'                 => "override-{$override->id}",
                'schedule_id'        => null,
                'exception_id'       => null,
                'usage_log_id'       => null,
                'override_id'        => $override->id,
                'room_id'            => $override->room_id,
                'room_code'          => $override->room?->code,
                'original_room_id'   => null,
                'original_room_code' => null,
                'event_date'         => $selectedDate,
                'source'             => 'override',
                'event_type'         => $status,
                'status'             => $status,
                'subject_code'       => 'ROOM',
                'subject_title'      => $title,
                'section'            => '',
                'instructor_name'    => null,
                'start_time'         => $this->normalizer->dateTimeToTime($effectiveStart, $dayStart),
                'end_time'           => $this->normalizer->dateTimeToTime($effectiveEnd, $dayEnd),
                'reason'             => $override->reason,
                'starts_at'          => $this->normalizer->dateTime($override->starts_at),
                'ends_at'            => $this->normalizer->dateTime($override->ends_at),
            ];
        });

        $dailySchedules = $regularItems
            ->concat($exceptionItems)
            ->concat($overrideItems)
            ->sortBy([
                ['start_time', 'asc'],
                ['room_id', 'asc'],
                ['source', 'asc'],
            ])
            ->values();

        return [
            'rooms'                => $rooms,
            'daily_schedules'      => $dailySchedules,
            'selected_date'        => $selectedDate,
            'operation_term_id'    => $operationTerm?->id,
            'claim_grace_minutes'  => $this->dailyOperationService->claimGraceMinutes(),
        ];
    }

    private function scheduleExceptionsForDate(string $selectedDate, int $academicTermId): Collection
    {
        return ScheduleException::query()
            ->with([
                'room:id,code,name,room_type,floor',
                'schedule:id,room_id,subject_code,subject_title,section,instructor_name,start_time,end_time',
                'schedule.room:id,code,name,room_type,floor',
            ])
            ->where('academic_term_id', $academicTermId)
            ->whereDate('event_date', $selectedDate)
            ->orderBy('start_time')
            ->get();
    }
}
