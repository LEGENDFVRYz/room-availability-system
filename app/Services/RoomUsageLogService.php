<?php

namespace App\Services;

use App\Models\RoomUsageLog;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Services\Support\ValueNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RoomUsageLogService
{
    public function __construct(
        private readonly ValueNormalizer $normalizer,
    ) {}

    public function logsBySlot(string $selectedDate): Collection
    {
        return RoomUsageLog::query()
            ->whereDate('usage_date', $selectedDate)
            ->get()
            ->keyBy(function (RoomUsageLog $log) {
                return match ($log->source) {
                    'schedule'           => "schedule-{$log->schedule_id}",
                    'schedule_exception' => "exception-{$log->schedule_exception_id}",
                    default              => "usage-{$log->id}",
                };
            });
    }

    public function applyStatusToItem(array $item, ?RoomUsageLog $usageLog): array
    {
        if (! $usageLog) {
            return $item;
        }

        $item['usage_log_id'] = $usageLog->id;

        $item['status'] = match ($this->normalizer->enumString($usageLog->status)) {
            'reserved'       => $item['status'],
            'occupied'       => 'ongoing',
            'completed'      => 'completed',
            'cancelled'      => 'cancelled',
            'auto_cancelled' => 'auto_cancelled',
            default          => $item['status'],
        };

        $item['actual_start'] = $this->normalizer->dateTime($usageLog->actual_start);
        $item['actual_end'] = $this->normalizer->dateTime($usageLog->actual_end);

        return $item;
    }

    public function existingScheduleUsageLog(Schedule $schedule, string $eventDate): ?RoomUsageLog
    {
        return RoomUsageLog::query()
            ->whereDate('usage_date', $eventDate)
            ->where('source', 'schedule')
            ->where('schedule_id', $schedule->id)
            ->first();
    }

    public function existingExceptionUsageLog(ScheduleException $exception): ?RoomUsageLog
    {
        return RoomUsageLog::query()
            ->whereDate('usage_date', $exception->event_date)
            ->where('source', 'schedule_exception')
            ->where('schedule_exception_id', $exception->id)
            ->first();
    }

    public function resetToAwaiting(RoomUsageLog $log, int $userId): RoomUsageLog
    {
        $log->status = 'reserved';
        $log->recorded_by = $userId;
        $log->actual_start = null;
        $log->actual_end = null;
        $log->save();

        return $log;
    }

    public function reopenCompleted(RoomUsageLog $log, int $userId): RoomUsageLog
    {
        $log->status = 'occupied';
        $log->recorded_by = $userId;
        $log->actual_end = null;

        if (! $log->actual_start) {
            $log->actual_start = now();
        }

        $log->save();

        return $log;
    }

    public function markScheduleUsage(
        Schedule $schedule,
        string $eventDate,
        string $status,
        int $userId,
        ?Carbon $actualStart = null,
        ?Carbon $actualEnd = null,
    ): RoomUsageLog {
        $log = $this->existingScheduleUsageLog($schedule, $eventDate) ?? new RoomUsageLog([
            'usage_date'      => $eventDate,
            'source'          => 'schedule',
            'schedule_id'     => $schedule->id,
            'room_id'         => $schedule->room_id,
            'subject_code'    => $schedule->subject_code,
            'subject_title'   => $schedule->subject_title,
            'section'         => $schedule->section,
            'instructor_name' => $schedule->instructor_name,
            'expected_start'  => $this->normalizer->time($schedule->start_time),
            'expected_end'    => $this->normalizer->time($schedule->end_time),
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
        $log = $this->existingExceptionUsageLog($exception) ?? new RoomUsageLog([
            'usage_date'             => $exception->event_date,
            'source'                 => 'schedule_exception',
            'schedule_id'            => $exception->schedule_id,
            'schedule_exception_id'  => $exception->id,
            'room_id'                => $exception->room_id,
            'subject_code'           => $exception->subject_code ?: ($exception->schedule?->subject_code ?? ''),
            'subject_title'          => $exception->subject_title ?: ($exception->schedule?->subject_title ?? ''),
            'section'                => $exception->section ?: ($exception->schedule?->section ?? ''),
            'instructor_name'        => $exception->instructor_name ?: $exception->schedule?->instructor_name,
            'expected_start'         => $this->normalizer->time($exception->start_time ?: $exception->schedule?->start_time),
            'expected_end'           => $this->normalizer->time($exception->end_time ?: $exception->schedule?->end_time),
        ]);

        return $this->applyUsageStatus($log, $status, $userId, $actualStart, $actualEnd);
    }

    public function roomUsageRecords(): Collection
    {
        return RoomUsageLog::query()
            ->with([
                'room:id,code,name,room_type',
                'scheduleException:id,event_type',
            ])
            ->whereIn('source', ['schedule', 'schedule_exception'])
            ->whereIn('status', ['occupied', 'completed'])
            ->whereNotNull('actual_start')
            ->orderByDesc('usage_date')
            ->orderByDesc('expected_start')
            ->orderByDesc('id')
            ->get()
            ->map(fn (RoomUsageLog $log) => $this->mapRoomUsageLog($log))
            ->values();
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

    private function mapRoomUsageLog(RoomUsageLog $log): array
    {
        return [
            'id'                    => $log->id,
            'usage_date'            => $this->normalizer->date($log->usage_date),
            'source'                => $log->source,
            'schedule_id'           => $log->schedule_id,
            'schedule_exception_id' => $log->schedule_exception_id,
            'room_id'               => $log->room_id,
            'room_code'             => $log->room?->code,
            'room_name'             => $log->room?->name,
            'borrow_type'           => $this->borrowType($log),
            'subject_code'          => $log->subject_code ?? '',
            'subject_title'         => $log->subject_title ?? '',
            'section'               => $log->section ?? '',
            'instructor_name'       => $log->instructor_name,
            'status'                => $log->status,
            'expected_start'        => $this->normalizer->time($log->expected_start),
            'expected_end'          => $this->normalizer->time($log->expected_end),
            'actual_start'          => $this->normalizer->dateTime($log->actual_start),
            'actual_end'            => $this->normalizer->dateTime($log->actual_end),
            'created_at'            => $this->normalizer->dateTime($log->created_at),
            'updated_at'            => $this->normalizer->dateTime($log->updated_at),
        ];
    }

    private function borrowType(RoomUsageLog $log): string
    {
        if ($log->source === 'schedule') {
            return 'regular';
        }

        $eventType = $this->normalizer->enumValue($log->scheduleException?->event_type);

        return $eventType ?: 'daily_operation';
    }
}
