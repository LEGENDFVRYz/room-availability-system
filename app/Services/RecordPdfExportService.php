<?php

namespace App\Services;

use App\Models\RoomOverride;
use App\Models\Room;
use App\Models\RoomUsageLog;
use App\Models\ScheduleException;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RecordPdfExportService
{
    public function roomUsageRecords(Request $request): Collection
    {
        $records = RoomUsageLog::query()
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
            ->map(fn (RoomUsageLog $log) => $this->mapRoomUsageLog($log));

        return $this->filterRoomUsageRecords($records, $request)
            ->map(fn (array $record) => $this->formatRoomUsageRecord($record))
            ->values();
    }

    public function activityRecords(Request $request): Collection
    {
        $records = $this->exceptionActivityRecords()
            ->concat($this->roomOverrideActivityRecords())
            ->sortByDesc(fn (array $record) => Carbon::parse($record['created_at'])->timestamp)
            ->values();

        return $this->filterActivityRecords($records, $request)
            ->map(fn (array $record) => $this->formatActivityRecord($record))
            ->values();
    }

    public function roomUsageFilterSummary(Request $request): array
    {
        $borrowType = (string) $request->query('borrow_type', 'all');

        return [
            'date_from' => $request->query('date_from') ?: null,
            'date_to' => $request->query('date_to') ?: null,
            'date_scope_label' => $this->dateScopeLabel($request),
            'room_label' => $this->roomFilterLabel($request),
            'search_label' => $this->searchFilterLabel($request),
            'borrow_type_label' => $borrowType === 'all' ? 'All valid types' : $this->label($borrowType),
        ];
    }

    public function activityFilterSummary(Request $request): array
    {
        $category = (string) $request->query('category', 'all');

        return [
            'date_from' => $request->query('date_from') ?: null,
            'date_to' => $request->query('date_to') ?: null,
            'date_scope_label' => $this->dateScopeLabel($request),
            'room_label' => $this->roomFilterLabel($request),
            'search_label' => $this->searchFilterLabel($request),
            'category_label' => $category === 'all' ? 'All activities' : $this->label($category),
        ];
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
        ];
    }

    private function exceptionActivityRecords(): Collection
    {
        return ScheduleException::query()
            ->with([
                'room:id,code,name,room_type',
                'schedule:id,room_id,subject_code,subject_title,section,instructor_name,start_time,end_time',
                'schedule.room:id,code,name,room_type',
                'createdBy:id,name,email',
                'updatedBy:id,name,email',
            ])
            ->latest('updated_at')
            ->get()
            ->flatMap(function (ScheduleException $exception) {
                $records = collect([$this->mapExceptionCreatedRecord($exception)]);

                if ($this->hasMeaningfulUpdate($exception)) {
                    $records->push($this->mapExceptionUpdatedRecord($exception));
                }

                return $records->filter();
            })
            ->values();
    }

    private function roomOverrideActivityRecords(): Collection
    {
        return RoomOverride::query()
            ->with([
                'room:id,code,name,room_type',
                'createdBy:id,name,email',
                'updatedBy:id,name,email',
            ])
            ->latest('updated_at')
            ->get()
            ->flatMap(function (RoomOverride $override) {
                $records = collect([$this->mapOverrideCreatedRecord($override)]);

                if ($this->hasMeaningfulUpdate($override)) {
                    $records->push($this->mapOverrideUpdatedRecord($override));
                }

                return $records->filter();
            })
            ->values();
    }

    private function mapExceptionCreatedRecord(ScheduleException $exception): array
    {
        $eventType = $this->enumValue($exception->event_type);
        $status = $this->enumValue($exception->status);

        return [
            'id'          => "exception-created-{$exception->id}",
            'created_at'  => $this->dateTimeValue($exception->created_at),
            'admin_name'  => $exception->createdBy?->name ?? 'System',
            'user_name'   => $exception->createdBy?->name,
            'action'      => $this->exceptionCreatedAction($eventType),
            'category'    => $this->exceptionCategory($eventType, $status),
            'entity_type' => 'ScheduleException',
            'entity_id'   => $exception->id,
            'room_id'     => $exception->room_id,
            'room_code'   => $exception->room?->code,
            'title'       => $this->exceptionCreatedTitle($eventType),
            'description' => $this->exceptionCreatedDescription($exception, $eventType),
            'details'     => $this->exceptionDetails($exception),
            'ip_address'  => null,
        ];
    }

    private function mapExceptionUpdatedRecord(ScheduleException $exception): array
    {
        $eventType = $this->enumValue($exception->event_type);
        $status = $this->enumValue($exception->status);

        return [
            'id'          => "exception-updated-{$exception->id}",
            'created_at'  => $this->dateTimeValue($exception->updated_at),
            'admin_name'  => $status === 'auto_cancelled'
                ? 'System'
                : ($exception->updatedBy?->name ?? $exception->createdBy?->name ?? 'System'),
            'user_name'   => $exception->updatedBy?->name,
            'action'      => $this->exceptionUpdatedAction($eventType, $status),
            'category'    => $status === 'auto_cancelled' ? 'system' : $this->exceptionCategory($eventType, $status),
            'entity_type' => 'ScheduleException',
            'entity_id'   => $exception->id,
            'room_id'     => $exception->room_id,
            'room_code'   => $exception->room?->code,
            'title'       => $this->exceptionUpdatedTitle($eventType, $status),
            'description' => $this->exceptionUpdatedDescription($exception, $eventType, $status),
            'details'     => $this->exceptionDetails($exception),
            'ip_address'  => null,
        ];
    }

    private function mapOverrideCreatedRecord(RoomOverride $override): array
    {
        $status = $this->enumValue($override->status);
        $roomCode = $override->room?->code;

        return [
            'id'          => "override-created-{$override->id}",
            'created_at'  => $this->dateTimeValue($override->created_at),
            'admin_name'  => $override->createdBy?->name ?? 'System',
            'user_name'   => $override->createdBy?->name,
            'action'      => 'override.created',
            'category'    => 'room_override',
            'entity_type' => 'RoomOverride',
            'entity_id'   => $override->id,
            'room_id'     => $override->room_id,
            'room_code'   => $roomCode,
            'title'       => $this->overrideTitle($status),
            'description' => "Created a {$this->label($status)} override for {$roomCode}.",
            'details'     => $this->overrideDetails($override),
            'ip_address'  => null,
        ];
    }

    private function mapOverrideUpdatedRecord(RoomOverride $override): array
    {
        $status = $this->enumValue($override->status);
        $isCleared = $this->overrideLooksCleared($override);
        $roomCode = $override->room?->code;

        return [
            'id'          => "override-updated-{$override->id}",
            'created_at'  => $this->dateTimeValue($override->updated_at),
            'admin_name'  => $override->updatedBy?->name ?? $override->createdBy?->name ?? 'System',
            'user_name'   => $override->updatedBy?->name,
            'action'      => $isCleared ? 'override.cleared' : 'override.updated',
            'category'    => 'room_override',
            'entity_type' => 'RoomOverride',
            'entity_id'   => $override->id,
            'room_id'     => $override->room_id,
            'room_code'   => $roomCode,
            'title'       => $isCleared ? 'Cleared room override' : 'Updated room override',
            'description' => $isCleared
                ? "Cleared the {$this->label($status)} override for {$roomCode}."
                : "Updated the {$this->label($status)} override for {$roomCode}.",
            'details'     => $this->overrideDetails($override),
            'ip_address'  => null,
        ];
    }

    private function filterRoomUsageRecords(Collection $records, Request $request): Collection
    {
        $search = strtolower(trim((string) $request->query('search', '')));
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $roomIds = $this->arrayQuery($request, 'room_ids');
        $borrowType = $request->query('borrow_type', 'all');

        return $records->filter(function (array $record) use ($search, $dateFrom, $dateTo, $roomIds, $borrowType) {
            $date = substr((string) ($record['usage_date'] ?? ''), 0, 10);

            if ($dateFrom && $date < $dateFrom) return false;
            if ($dateTo && $date > $dateTo) return false;
            if (! empty($roomIds) && ! in_array((string) ($record['room_id'] ?? ''), $roomIds, true)) return false;
            if ($borrowType !== 'all' && ($record['borrow_type'] ?? null) !== $borrowType) return false;

            if ($search !== '') {
                $haystack = strtolower(implode(' ', array_filter([
                    $record['room_code'] ?? null,
                    $record['subject_code'] ?? null,
                    $record['subject_title'] ?? null,
                    $record['section'] ?? null,
                    $record['instructor_name'] ?? null,
                ])));

                return str_contains($haystack, $search);
            }

            return true;
        })->values();
    }

    private function filterActivityRecords(Collection $records, Request $request): Collection
    {
        $search = strtolower(trim((string) $request->query('search', '')));
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $roomIds = $this->arrayQuery($request, 'room_ids');
        $category = $request->query('category', 'all');

        return $records->filter(function (array $record) use ($search, $dateFrom, $dateTo, $roomIds, $category) {
            $date = substr((string) ($record['created_at'] ?? ''), 0, 10);

            if ($dateFrom && $date < $dateFrom) return false;
            if ($dateTo && $date > $dateTo) return false;
            if (! empty($roomIds) && ! in_array((string) ($record['room_id'] ?? ''), $roomIds, true)) return false;
            if ($category !== 'all' && ($record['category'] ?? null) !== $category) return false;

            if ($search !== '') {
                $haystack = strtolower(implode(' ', array_filter([
                    $record['admin_name'] ?? null,
                    $record['user_name'] ?? null,
                    $record['action'] ?? null,
                    $record['category'] ?? null,
                    $record['room_code'] ?? null,
                    $record['title'] ?? null,
                    $record['description'] ?? null,
                    $record['details'] ?? null,
                    $record['entity_type'] ?? null,
                    $record['ip_address'] ?? null,
                ])));

                return str_contains($haystack, $search);
            }

            return true;
        })->values();
    }

    private function borrowType(RoomUsageLog $log): string
    {
        if ($log->source === 'schedule') {
            return 'regular';
        }

        return $this->enumValue($log->scheduleException?->event_type) ?: 'daily_operation';
    }

    private function formatRoomUsageRecord(array $record): array
    {
        $record['usage_date_label'] = $this->dateLabel($record['usage_date'] ?? null);
        $record['borrow_type_label'] = $this->label($record['borrow_type'] ?? 'borrowed room');
        $record['status_label'] = match ($record['status'] ?? '') {
            'occupied' => 'In Use',
            'completed' => 'Completed',
            default => $this->label($record['status'] ?? 'Status'),
        };
        $record['expected_start_label'] = $this->timeLabel($record['expected_start'] ?? null);
        $record['expected_end_label'] = $this->timeLabel($record['expected_end'] ?? null);
        $record['actual_start_label'] = $this->timeLabel($record['actual_start'] ?? null);
        $record['actual_end_label'] = $this->timeLabel($record['actual_end'] ?? null) ?: 'In progress';

        return $record;
    }

    private function formatActivityRecord(array $record): array
    {
        $record['category_label'] = $this->label($record['category'] ?? 'activity');
        $record['created_at_label'] = $this->dateTimeLabel($record['created_at'] ?? null);

        return $record;
    }

    private function exceptionCategory(string $eventType, string $status): string
    {
        if ($eventType === 'cancellation' || $status === 'cancelled') return 'cancellation';
        if ($eventType === 'room_change') return 'room_change';
        if (in_array($eventType, ['special_class', 'makeup_class'], true)) return 'class_request';

        return 'daily_exception';
    }

    private function exceptionCreatedAction(string $eventType): string
    {
        return match ($eventType) {
            'cancellation' => 'schedule.cancelled',
            'room_change' => 'schedule.room_changed',
            'special_class' => 'exception.special_class_created',
            'makeup_class' => 'exception.makeup_class_created',
            default => 'exception.created',
        };
    }

    private function exceptionUpdatedAction(string $eventType, string $status): string
    {
        if ($status === 'auto_cancelled') return 'system.auto_cancelled';
        if ($status === 'cancelled' && $eventType !== 'cancellation') return 'exception.cancelled';

        return match ($eventType) {
            'room_change' => 'schedule.room_change_updated',
            'special_class' => 'exception.special_class_updated',
            'makeup_class' => 'exception.makeup_class_updated',
            default => 'exception.updated',
        };
    }

    private function exceptionCreatedTitle(string $eventType): string
    {
        return match ($eventType) {
            'cancellation' => 'Cancelled scheduled class',
            'room_change' => 'Changed room for today',
            'special_class' => 'Added special class',
            'makeup_class' => 'Added makeup class',
            default => 'Created daily exception',
        };
    }

    private function exceptionUpdatedTitle(string $eventType, string $status): string
    {
        if ($status === 'auto_cancelled') return 'Auto-cancelled unclaimed class';
        if ($status === 'cancelled' && $eventType !== 'cancellation') return 'Cancelled daily exception';

        return match ($eventType) {
            'room_change' => 'Updated room change',
            'special_class' => 'Updated special class',
            'makeup_class' => 'Updated makeup class',
            default => 'Updated daily exception',
        };
    }

    private function exceptionCreatedDescription(ScheduleException $exception, string $eventType): string
    {
        $subject = $this->subjectLabel($exception);
        $section = $exception->section ?: $exception->schedule?->section;
        $roomCode = $exception->room?->code ?? 'selected room';

        if ($eventType === 'room_change') {
            $fromRoom = $exception->schedule?->room?->code ?? 'original room';
            return "Moved {$subject} for {$section} from {$fromRoom} to {$roomCode} for the selected date only.";
        }

        if ($eventType === 'cancellation') return "Cancelled {$subject} for {$section} on the selected date.";
        if ($eventType === 'special_class') return "Created a one-off special class for {$subject} in {$roomCode}.";
        if ($eventType === 'makeup_class') return "Created a makeup class for {$subject} in {$roomCode}.";

        return "Created a daily exception for {$subject} in {$roomCode}.";
    }

    private function exceptionUpdatedDescription(ScheduleException $exception, string $eventType, string $status): string
    {
        $subject = $this->subjectLabel($exception);
        $section = $exception->section ?: $exception->schedule?->section;

        if ($status === 'auto_cancelled') return "Auto-cancelled {$subject} for {$section} because it was not claimed within the grace period.";
        if ($status === 'cancelled' && $eventType !== 'cancellation') return "Cancelled the daily exception for {$subject} and {$section}.";

        return "Updated the daily exception for {$subject} and {$section}.";
    }

    private function exceptionDetails(ScheduleException $exception): string
    {
        $parts = [
            'Type: '.$this->label($this->enumValue($exception->event_type)),
            'Status: '.$this->label($this->enumValue($exception->status)),
            'Schedule: '.$this->dateValue($exception->event_date).' · '.$this->timeValue($exception->start_time ?: $exception->schedule?->start_time).' - '.$this->timeValue($exception->end_time ?: $exception->schedule?->end_time),
            'Instructor: '.($exception->instructor_name ?: $exception->schedule?->instructor_name ?: 'Not listed'),
        ];

        if (filled($exception->reason)) $parts[] = 'Reason: '.$exception->reason;

        return implode(' | ', $parts);
    }

    private function overrideTitle(string $status): string
    {
        return match ($status) {
            'maintenance' => 'Set room to maintenance',
            'unavailable' => 'Set room to unavailable',
            'reserved' => 'Reserved room',
            default => 'Created room override',
        };
    }

    private function overrideDetails(RoomOverride $override): string
    {
        $parts = [
            'Status: '.$this->label($this->enumValue($override->status)),
            'Window: '.$this->dateTimeLabel($this->dateTimeValue($override->starts_at)).' - '.($this->dateTimeLabel($this->dateTimeValue($override->ends_at)) ?: 'Until cleared'),
        ];

        if (filled($override->reason)) $parts[] = 'Reason: '.$override->reason;

        return implode(' | ', $parts);
    }

    private function hasMeaningfulUpdate(object $model): bool
    {
        if (! $model->created_at || ! $model->updated_at) return false;

        return $model->updated_at->gt($model->created_at->copy()->addSecond());
    }

    private function overrideLooksCleared(RoomOverride $override): bool
    {
        if (! $override->is_active) return true;
        if (! $override->ends_at || ! $override->updated_at) return false;

        return abs($override->ends_at->diffInMinutes($override->updated_at, false)) <= 5;
    }

    private function subjectLabel(ScheduleException $exception): string
    {
        return trim(($exception->subject_code ?: $exception->schedule?->subject_code ?: 'Class').' '.($exception->subject_title ?: $exception->schedule?->subject_title ?: ''));
    }

    private function dateScopeLabel(Request $request): string
    {
        $from = $request->query('date_from');
        $to = $request->query('date_to');

        if ($from && $to) {
            return $this->dateLabel((string) $from).' to '.$this->dateLabel((string) $to);
        }

        if ($from) {
            return 'From '.$this->dateLabel((string) $from);
        }

        if ($to) {
            return 'Until '.$this->dateLabel((string) $to);
        }

        return 'All dates';
    }

    private function searchFilterLabel(Request $request): string
    {
        $search = trim((string) $request->query('search', ''));

        return $search !== '' ? $search : 'None';
    }

    private function roomFilterLabel(Request $request): string
    {
        $roomIds = $this->arrayQuery($request, 'room_ids');

        if (empty($roomIds)) {
            return 'All rooms';
        }

        $codes = Room::query()
            ->whereIn('id', $roomIds)
            ->orderBy('display_order')
            ->orderBy('code')
            ->pluck('code')
            ->filter()
            ->values();

        if ($codes->isEmpty()) {
            return 'Selected rooms: '.implode(', ', $roomIds);
        }

        return $codes->join(', ');
    }

    private function arrayQuery(Request $request, string $key): array
    {
        $value = $request->query($key, []);

        if (is_string($value)) return array_values(array_filter(explode(',', $value), fn (string $item) => trim($item) !== ''));
        if (is_array($value)) return array_map('strval', $value);

        return [];
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof BackedEnum ? $value->value : (string) $value;
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value === null) return null;
        if ($value instanceof Carbon) return $value->toDateString();

        return substr((string) $value, 0, 10);
    }

    private function dateTimeValue(mixed $value): ?string
    {
        if ($value === null) return null;
        if ($value instanceof Carbon) return $value->toIso8601String();

        return (string) $value;
    }

    private function timeValue(mixed $value): ?string
    {
        if ($value === null) return null;
        if ($value instanceof Carbon) return $value->format('H:i');

        return substr((string) $value, 0, 5);
    }

    private function timeLabel(?string $value): ?string
    {
        if (! $value) return null;

        try {
            return Carbon::parse($value)->format('h:i A');
        } catch (\Throwable) {
            return substr($value, 0, 5);
        }
    }

    private function dateLabel(?string $value): ?string
    {
        if (! $value) return null;

        try {
            return Carbon::parse($value)->format('M d, Y');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function dateTimeLabel(?string $value): ?string
    {
        if (! $value) return null;

        try {
            return Carbon::parse($value)->format('M d, Y h:i A');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function label(string $value): string
    {
        return str($value)->replace(['_', '.'], ' ')->title()->toString();
    }
}
