<?php

namespace App\Services;

use App\Models\RoomOverride;
use App\Models\ScheduleException;
use App\Services\Support\ValueNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ActivityLogService
{
    public function __construct(
        private readonly ValueNormalizer $normalizer,
    ) {}

    /**
     * Admin Activity Logs are derived from admin decision tables for now.
     *
     * This intentionally does not query room_usage_logs because usage logs are
     * only for valid classroom borrowing records.
     */
    public function records(): Collection
    {
        return $this->exceptionActivityRecords()
            ->concat($this->roomOverrideActivityRecords())
            ->sortByDesc(fn (array $record) => Carbon::parse($record['created_at'])->timestamp)
            ->values();
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
                $records = collect([
                    $this->mapExceptionCreatedRecord($exception),
                ]);

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
                $records = collect([
                    $this->mapOverrideCreatedRecord($override),
                ]);

                if ($this->hasMeaningfulUpdate($override)) {
                    $records->push($this->mapOverrideUpdatedRecord($override));
                }

                return $records->filter();
            })
            ->values();
    }

    private function mapExceptionCreatedRecord(ScheduleException $exception): array
    {
        $eventType = $this->normalizer->enumString($exception->event_type);
        $status = $this->normalizer->enumString($exception->status);
        $category = $this->exceptionCategory($eventType, $status);
        $roomCode = $exception->room?->code;

        return [
            'id'          => "exception-created-{$exception->id}",
            'created_at'  => $this->normalizer->dateTime($exception->created_at),
            'admin_name'  => $exception->createdBy?->name ?? 'System',
            'user_name'   => $exception->createdBy?->name,
            'action'      => $this->exceptionCreatedAction($eventType),
            'category'    => $category,
            'entity_type' => 'ScheduleException',
            'entity_id'   => $exception->id,
            'room_id'     => $exception->room_id,
            'room_code'   => $roomCode,
            'title'       => $this->exceptionCreatedTitle($eventType),
            'description' => $this->exceptionCreatedDescription($exception, $eventType),
            'details'     => $this->exceptionDetails($exception),
            'metadata'    => $this->exceptionMetadata($exception),
            'ip_address'  => null,
        ];
    }

    private function mapExceptionUpdatedRecord(ScheduleException $exception): array
    {
        $eventType = $this->normalizer->enumString($exception->event_type);
        $status = $this->normalizer->enumString($exception->status);
        $category = $this->exceptionUpdateCategory($eventType, $status);

        return [
            'id'          => "exception-updated-{$exception->id}",
            'created_at'  => $this->normalizer->dateTime($exception->updated_at),
            'admin_name'  => $status === 'auto_cancelled'
                ? 'System'
                : ($exception->updatedBy?->name ?? $exception->createdBy?->name ?? 'System'),
            'user_name'   => $exception->updatedBy?->name,
            'action'      => $this->exceptionUpdatedAction($eventType, $status),
            'category'    => $category,
            'entity_type' => 'ScheduleException',
            'entity_id'   => $exception->id,
            'room_id'     => $exception->room_id,
            'room_code'   => $exception->room?->code,
            'title'       => $this->exceptionUpdatedTitle($eventType, $status),
            'description' => $this->exceptionUpdatedDescription($exception, $eventType, $status),
            'details'     => $this->exceptionDetails($exception),
            'metadata'    => $this->exceptionMetadata($exception),
            'ip_address'  => null,
        ];
    }

    private function mapOverrideCreatedRecord(RoomOverride $override): array
    {
        $status = $this->normalizer->enumString($override->status);
        $roomCode = $override->room?->code;

        return [
            'id'          => "override-created-{$override->id}",
            'created_at'  => $this->normalizer->dateTime($override->created_at),
            'admin_name'  => $override->createdBy?->name ?? 'System',
            'user_name'   => $override->createdBy?->name,
            'action'      => 'override.created',
            'category'    => 'room_override',
            'entity_type' => 'RoomOverride',
            'entity_id'   => $override->id,
            'room_id'     => $override->room_id,
            'room_code'   => $roomCode,
            'title'       => $this->overrideTitle($status),
            'description' => "Created a {$this->humanize($status)} override for {$roomCode}.",
            'details'     => $this->overrideDetails($override),
            'metadata'    => $this->overrideMetadata($override),
            'ip_address'  => null,
        ];
    }

    private function mapOverrideUpdatedRecord(RoomOverride $override): array
    {
        $status = $this->normalizer->enumString($override->status);
        $isCleared = $this->overrideLooksCleared($override);
        $roomCode = $override->room?->code;

        return [
            'id'          => "override-updated-{$override->id}",
            'created_at'  => $this->normalizer->dateTime($override->updated_at),
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
                ? "Cleared the {$this->humanize($status)} override for {$roomCode}."
                : "Updated the {$this->humanize($status)} override for {$roomCode}.",
            'details'     => $this->overrideDetails($override),
            'metadata'    => $this->overrideMetadata($override),
            'ip_address'  => null,
        ];
    }

    private function hasMeaningfulUpdate(object $model): bool
    {
        if (! $model->created_at || ! $model->updated_at) {
            return false;
        }

        return $model->updated_at->gt($model->created_at->copy()->addSecond());
    }

    private function exceptionCategory(string $eventType, string $status): string
    {
        if ($eventType === 'cancellation' || $status === 'cancelled') {
            return 'cancellation';
        }

        if ($eventType === 'room_change') {
            return 'room_change';
        }

        if (in_array($eventType, ['special_class', 'makeup_class'], true)) {
            return 'class_request';
        }

        return 'daily_exception';
    }

    private function exceptionUpdateCategory(string $eventType, string $status): string
    {
        if ($status === 'auto_cancelled') {
            return 'system';
        }

        if ($status === 'cancelled') {
            return 'cancellation';
        }

        return $this->exceptionCategory($eventType, $status);
    }

    private function exceptionCreatedAction(string $eventType): string
    {
        return match ($eventType) {
            'cancellation'   => 'schedule.cancelled',
            'room_change'    => 'schedule.room_changed',
            'special_class'  => 'exception.special_class_created',
            'makeup_class'   => 'exception.makeup_class_created',
            default          => 'exception.created',
        };
    }

    private function exceptionUpdatedAction(string $eventType, string $status): string
    {
        if ($status === 'auto_cancelled') {
            return 'system.auto_cancelled';
        }

        if ($status === 'cancelled' && $eventType !== 'cancellation') {
            return 'exception.cancelled';
        }

        return match ($eventType) {
            'room_change'   => 'schedule.room_change_updated',
            'special_class' => 'exception.special_class_updated',
            'makeup_class'  => 'exception.makeup_class_updated',
            default         => 'exception.updated',
        };
    }

    private function exceptionCreatedTitle(string $eventType): string
    {
        return match ($eventType) {
            'cancellation'   => 'Cancelled scheduled class',
            'room_change'    => 'Changed room for today',
            'special_class'  => 'Added special class',
            'makeup_class'   => 'Added makeup class',
            default          => 'Created daily exception',
        };
    }

    private function exceptionUpdatedTitle(string $eventType, string $status): string
    {
        if ($status === 'auto_cancelled') {
            return 'Auto-cancelled unclaimed class';
        }

        if ($status === 'cancelled' && $eventType !== 'cancellation') {
            return 'Cancelled daily exception';
        }

        return match ($eventType) {
            'room_change'   => 'Updated room change',
            'special_class' => 'Updated special class',
            'makeup_class'  => 'Updated makeup class',
            default         => 'Updated daily exception',
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

        if ($eventType === 'cancellation') {
            return "Cancelled {$subject} for {$section} on the selected date.";
        }

        if ($eventType === 'special_class') {
            return "Created a one-off special class for {$subject} in {$roomCode}.";
        }

        if ($eventType === 'makeup_class') {
            return "Created a makeup class for {$subject} in {$roomCode}.";
        }

        return "Created a daily exception for {$subject} in {$roomCode}.";
    }

    private function exceptionUpdatedDescription(ScheduleException $exception, string $eventType, string $status): string
    {
        $subject = $this->subjectLabel($exception);
        $section = $exception->section ?: $exception->schedule?->section;

        if ($status === 'auto_cancelled') {
            return "Auto-cancelled {$subject} for {$section} because it was not claimed within the grace period.";
        }

        if ($status === 'cancelled' && $eventType !== 'cancellation') {
            return "Cancelled the daily exception for {$subject} and {$section}.";
        }

        return "Updated the daily exception for {$subject} and {$section}.";
    }

    private function exceptionDetails(ScheduleException $exception): string
    {
        $parts = [
            'Type: '.$this->humanize($this->normalizer->enumString($exception->event_type)),
            'Status: '.$this->humanize($this->normalizer->enumString($exception->status)),
            'Schedule: '.$this->formatDate($exception->event_date).' · '.$this->timeRange($exception->start_time ?: $exception->schedule?->start_time, $exception->end_time ?: $exception->schedule?->end_time),
            'Instructor: '.($exception->instructor_name ?: $exception->schedule?->instructor_name ?: 'Not listed'),
        ];

        if (filled($exception->reason)) {
            $parts[] = 'Reason: '.$exception->reason;
        }

        return implode(' | ', $parts);
    }

    private function overrideTitle(string $status): string
    {
        return match ($status) {
            'maintenance' => 'Set room to maintenance',
            'unavailable' => 'Set room to unavailable',
            'reserved'    => 'Reserved room',
            default       => 'Created room override',
        };
    }

    private function overrideDetails(RoomOverride $override): string
    {
        $parts = [
            'Status: '.$this->humanize($this->normalizer->enumString($override->status)),
            'Window: '.$this->timeRange($override->starts_at, $override->ends_at).' '.($override->ends_at ? '' : '(indefinite)'),
        ];

        if (filled($override->reason)) {
            $parts[] = 'Reason: '.$override->reason;
        }

        return implode(' | ', $parts);
    }

    private function exceptionMetadata(ScheduleException $exception): array
    {
        return [
            'event_type'       => $this->normalizer->enumString($exception->event_type),
            'status'           => $this->normalizer->enumString($exception->status),
            'schedule_id'      => $exception->schedule_id,
            'event_date'       => $this->normalizer->date($exception->event_date),
            'start_time'       => $this->normalizer->time($exception->start_time ?: $exception->schedule?->start_time),
            'end_time'         => $this->normalizer->time($exception->end_time ?: $exception->schedule?->end_time),
            'subject_code'     => $exception->subject_code ?: $exception->schedule?->subject_code,
            'section'          => $exception->section ?: $exception->schedule?->section,
        ];
    }

    private function overrideMetadata(RoomOverride $override): array
    {
        return [
            'status'    => $this->normalizer->enumString($override->status),
            'starts_at' => $this->normalizer->dateTime($override->starts_at),
            'ends_at'   => $this->normalizer->dateTime($override->ends_at),
            'is_active' => $override->is_active,
        ];
    }

    private function overrideLooksCleared(RoomOverride $override): bool
    {
        if (! $override->is_active) {
            return true;
        }

        if (! $override->ends_at || ! $override->updated_at) {
            return false;
        }

        return abs($override->ends_at->diffInMinutes($override->updated_at, false)) <= 5;
    }

    private function subjectLabel(ScheduleException $exception): string
    {
        return trim(($exception->subject_code ?: $exception->schedule?->subject_code ?: 'Class').' '.($exception->subject_title ?: $exception->schedule?->subject_title ?: ''));
    }

    private function formatDate(mixed $value): string
    {
        $date = $this->normalizer->date($value);

        if (! $date) {
            return 'No date';
        }

        return Carbon::parse($date)->format('M d, Y');
    }

    private function timeRange(mixed $start, mixed $end): string
    {
        $startTime = $this->normalizer->time($start);
        $endTime = $this->normalizer->time($end);

        return ($startTime ?: '—').'–'.($endTime ?: '—');
    }

    private function humanize(string $value): string
    {
        return str($value ?: 'unknown')->replace('_', ' ')->title()->toString();
    }
}
