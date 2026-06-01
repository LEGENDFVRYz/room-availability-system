<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomUsageLog;
use BackedEnum;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class RecordController extends Controller
{
    public function roomUsage(): Response
    {
        $logs = RoomUsageLog::query()
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

        $rooms = Room::query()
            ->select('id', 'code', 'name', 'room_type', 'display_order')
            ->orderBy('display_order')
            ->orderBy('code')
            ->get()
            ->map(fn (Room $room) => [
                'id'        => $room->id,
                'code'      => $room->code,
                'name'      => $room->name,
                'room_type' => $this->enumValue($room->room_type),
            ])
            ->values();

        return Inertia::render('Admin/Records/UsageLog', [
            'logs'  => $logs,
            'rooms' => $rooms,
        ]);
    }

    public function activity(): Response
    {
        return Inertia::render('Admin/Records/ActivityLog');
    }

    private function mapRoomUsageLog(RoomUsageLog $log): array
    {
        return [
            'id'                    => $log->id,
            'usage_date'            => $this->dateValue($log->usage_date),
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
            'expected_start'        => $this->timeValue($log->expected_start),
            'expected_end'          => $this->timeValue($log->expected_end),
            'actual_start'          => $this->dateTimeValue($log->actual_start),
            'actual_end'            => $this->dateTimeValue($log->actual_end),
            'created_at'            => $this->dateTimeValue($log->created_at),
            'updated_at'            => $this->dateTimeValue($log->updated_at),
        ];
    }

    private function borrowType(RoomUsageLog $log): string
    {
        if ($log->source === 'schedule') {
            return 'regular';
        }

        $eventType = $this->enumValue($log->scheduleException?->event_type);

        return $eventType ?: 'daily_operation';
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        return substr((string) $value, 0, 10);
    }

    private function dateTimeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }

        return (string) $value;
    }

    private function timeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('H:i');
        }

        return substr((string) $value, 0, 5);
    }
}
