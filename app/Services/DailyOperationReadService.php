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
        $operationTerm = $this->academicTermService->getCurrentOrActive($date);

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

        $payload = [
            'rooms'                => $rooms,
            'daily_schedules'      => $dailySchedules,
            'selected_date'        => $selectedDate,
            'operation_term_id'    => $operationTerm?->id,
            'claim_grace_minutes'  => $this->dailyOperationService->claimGraceMinutes(),
        ];

        $payload['room_stats'] = $this->roomStatsFromPayload($payload);

        return $payload;
    }

    public function roomStatsFromPayload(array $payload): array
    {
        $rooms = collect($payload['rooms'] ?? []);
        $dailySchedules = collect($payload['daily_schedules'] ?? []);
        $selectedDate = (string) ($payload['selected_date'] ?? now()->toDateString());
        $claimGraceMinutes = max(1, (int) ($payload['claim_grace_minutes'] ?? $this->dailyOperationService->claimGraceMinutes()));

        $activeRoomIds = $rooms
            ->filter(fn (array $room) => (bool) ($room['is_active'] ?? false))
            ->pluck('id')
            ->filter()
            ->map(fn ($roomId) => (int) $roomId)
            ->unique()
            ->values();

        $occupiedRoomIds = collect();
        $claimableRoomIds = collect();
        $currentOverrideRoomIds = collect();

        foreach ($dailySchedules as $item) {
            if (! is_array($item) || empty($item['room_id'])) {
                continue;
            }

            $roomId = (int) $item['room_id'];
            $source = (string) ($item['source'] ?? '');
            $status = (string) ($item['status'] ?? '');
            $eventType = (string) ($item['event_type'] ?? '');

            if ($source === 'override') {
                if ($this->itemIsCurrent($item, $selectedDate)) {
                    $currentOverrideRoomIds->push($roomId);
                }

                continue;
            }

            if ($eventType === 'cancellation' || in_array($status, ['cancelled', 'auto_cancelled', 'completed'], true)) {
                continue;
            }

            if (in_array($status, ['ongoing', 'occupied'], true)) {
                $occupiedRoomIds->push($roomId);
                continue;
            }

            if ($this->itemIsClaimableNow($item, $selectedDate, $claimGraceMinutes)) {
                $claimableRoomIds->push($roomId);
            }
        }

        $occupiedRoomIds = $occupiedRoomIds->unique()->values();
        $claimableRoomIds = $claimableRoomIds
            ->unique()
            ->reject(fn (int $roomId) => $occupiedRoomIds->contains($roomId))
            ->values();
        $currentOverrideRoomIds = $currentOverrideRoomIds->unique()->values();

        $blockedRoomIds = $occupiedRoomIds
            ->merge($claimableRoomIds)
            ->merge($currentOverrideRoomIds)
            ->unique()
            ->values();

        $riskOrExceptionCount = $dailySchedules
            ->filter(fn (array $item) => in_array((string) ($item['source'] ?? ''), ['exception', 'override'], true))
            ->count();

        return [
            'available'   => max(0, $activeRoomIds->count() - $blockedRoomIds->count()),
            'occupied'    => $occupiedRoomIds->count(),
            'reserved'    => $claimableRoomIds->count(),
            'maintenance' => $riskOrExceptionCount,
        ];
    }

    private function itemIsCurrent(array $item, string $selectedDate): bool
    {
        if (! $this->selectedDateIsToday($selectedDate)) {
            return false;
        }

        $start = $this->dateTimeForItemTime($selectedDate, $item['start_time'] ?? null);
        $end = $this->dateTimeForItemTime($selectedDate, $item['end_time'] ?? null, endOfDayFallback: true);

        if (! $start || ! $end || $start->greaterThanOrEqualTo($end)) {
            return false;
        }

        $now = now();

        return $now->greaterThanOrEqualTo($start) && $now->lessThan($end);
    }

    private function itemIsClaimableNow(array $item, string $selectedDate, int $claimGraceMinutes): bool
    {
        if (! $this->selectedDateIsToday($selectedDate)) {
            return false;
        }

        $status = (string) ($item['status'] ?? '');

        if (! in_array($status, ['scheduled', 'pending', 'reserved'], true)) {
            return false;
        }

        $start = $this->dateTimeForItemTime($selectedDate, $item['start_time'] ?? null);
        $end = $this->dateTimeForItemTime($selectedDate, $item['end_time'] ?? null, endOfDayFallback: true);

        if (! $start || ! $end || $start->greaterThanOrEqualTo($end)) {
            return false;
        }

        $claimDeadline = $this->dateTimeValue($item['auto_cancel_at'] ?? null)
            ?? $start->copy()->addMinutes($claimGraceMinutes);

        if ($claimDeadline->greaterThan($end)) {
            $claimDeadline = $end;
        }

        $now = now();

        return $now->greaterThanOrEqualTo($start) && $now->lessThan($claimDeadline);
    }

    private function selectedDateIsToday(string $selectedDate): bool
    {
        try {
            return Carbon::parse($selectedDate)->isSameDay(now());
        } catch (\Throwable) {
            return true;
        }
    }

    private function dateTimeForItemTime(string $selectedDate, mixed $time, bool $endOfDayFallback = false): ?Carbon
    {
        $minutes = $this->minutes($time);

        if ($minutes === null) {
            return null;
        }

        try {
            return Carbon::parse($selectedDate)
                ->startOfDay()
                ->addMinutes($minutes === 0 && $endOfDayFallback ? 1440 : $minutes);
        } catch (\Throwable) {
            return null;
        }
    }

    private function dateTimeValue(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function minutes(mixed $time): ?int
    {
        if ($time === null) {
            return null;
        }

        if ($time instanceof Carbon) {
            return ($time->hour * 60) + $time->minute;
        }

        if (! preg_match('/^(\d{1,2}):(\d{2})/', (string) $time, $matches)) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return null;
        }

        return ($hour * 60) + $minute;
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
