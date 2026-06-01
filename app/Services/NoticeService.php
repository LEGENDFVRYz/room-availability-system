<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Notice;
use App\Models\Room;
use App\Models\RoomOverride;
use App\Models\Schedule;
use App\Models\ScheduleException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class NoticeService
{
    /**
     * Public notice lifetimes are intentionally short for generated notices so
     * kiosk viewers do not keep seeing old operational information.
     */
    private const DEFAULT_GENERAL_DAYS = 7;

    public function announceAcademicTermSet(AcademicTerm $term, ?int $userId = null): Notice
    {
        $termLabel = $this->termLabel($term);

        return $this->publish(
            identity: [
                'source_type' => 'academic_term',
                'source_id' => $term->id,
            ],
            data: [
                'title' => 'New academic term is now active',
                'body' => "The active academic term is now {$termLabel}. Room schedules now follow this term configuration.",
                'type' => 'academic_term',
                'room_id' => null,
                'metadata' => [
                    'term_label' => $termLabel,
                    'audience' => 'students_faculty_viewers',
                ],
                'starts_at' => now(),
                'ends_at' => now()->addDays(self::DEFAULT_GENERAL_DAYS),
                'is_pinned' => true,
            ],
            userId: $userId,
        );
    }

    public function announceScheduleUpdated(Schedule $schedule, string $action = 'updated', ?int $userId = null): Notice
    {
        $schedule->loadMissing(['room:id,code,name', 'academicTerm']);

        $sectionSubject = $this->sectionSubjectLabel(
            section: $schedule->section,
            subjectTitle: $schedule->subject_title,
            subjectCode: $schedule->subject_code,
        );

        $actionLabel = match ($action) {
            'created', 'added' => 'added',
            'deleted', 'removed' => 'removed',
            default => 'updated',
        };

        return $this->publish(
            identity: [
                'source_type' => 'schedule',
                'source_id' => $schedule->id,
            ],
            data: [
                'title' => "Weekly schedule {$actionLabel} for {$schedule->section}",
                'body' => "The weekly schedule entry for {$sectionSubject} was {$actionLabel}. Please check the latest room availability before proceeding to a room.",
                'type' => 'schedule_update',
                'room_id' => $schedule->room_id,
                'metadata' => [
                    'section' => $schedule->section,
                    'subject_code' => $schedule->subject_code,
                    'subject_title' => $schedule->subject_title,
                    'room_label' => $this->roomLabel($schedule->room),
                    'schedule_label' => $this->dayTimeLabel($schedule->day_of_week, $schedule->start_time, $schedule->end_time),
                    'action' => $actionLabel,
                ],
                'starts_at' => now(),
                'ends_at' => now()->addDays(self::DEFAULT_GENERAL_DAYS),
                'is_pinned' => false,
            ],
            userId: $userId,
        );
    }

    public function announceClassException(
        ScheduleException $exception,
        ?string $action = null,
        ?int $userId = null,
        ?int $fromRoomId = null,
    ): Notice {
        $exception->loadMissing([
            'room:id,code,name',
            'schedule:id,room_id,subject_code,subject_title,section,instructor_name,start_time,end_time',
            'schedule.room:id,code,name',
        ]);

        $eventType = $this->enumString($exception->event_type);
        $noticeType = $this->exceptionNoticeType($eventType, $action);
        $sectionSubject = $this->sectionSubjectLabel(
            section: $exception->section ?: $exception->schedule?->section,
            subjectTitle: $exception->subject_title ?: $exception->schedule?->subject_title,
            subjectCode: $exception->subject_code ?: $exception->schedule?->subject_code,
        );

        $roomLabel = $this->roomLabel($exception->room);
        $fromRoom = $fromRoomId ? Room::query()->find($fromRoomId) : $exception->schedule?->room;
        $fromRoomLabel = $this->roomLabel($fromRoom);
        $scheduleLabel = $this->timeRangeLabel($exception->start_time ?: $exception->schedule?->start_time, $exception->end_time ?: $exception->schedule?->end_time);

        [$title, $body] = match ($noticeType) {
            'class_cancellation' => [
                "{$sectionSubject} class cancelled today",
                'This class is cancelled for the selected date only. Please check the room board before proceeding, as the room may become available after the protected schedule window.',
            ],
            'room_change' => [
                "{$sectionSubject} moved to {$roomLabel}",
                'A same day room change was approved for this class. Students and faculty should proceed to the replacement room shown below.',
            ],
            default => [
                "{$sectionSubject} " . ($eventType === 'makeup_class' ? 'makeup class' : 'special class') . ' added',
                'A special or makeup class was added for this section. Please follow the assigned room and time shown below.',
            ],
        };

        $metadata = [
            'section' => $exception->section ?: $exception->schedule?->section,
            'subject_code' => $exception->subject_code ?: $exception->schedule?->subject_code,
            'subject_title' => $exception->subject_title ?: $exception->schedule?->subject_title,
            'room_label' => $noticeType === 'room_change' && $fromRoomLabel
                ? trim("{$fromRoomLabel} → {$roomLabel}")
                : $roomLabel,
            'schedule_label' => $scheduleLabel,
            'event_date' => $this->dateString($exception->event_date),
            'event_type' => $eventType,
        ];

        if ($noticeType === 'room_change') {
            $metadata['from_room_id'] = $fromRoomId ?: $exception->schedule?->room_id;
            $metadata['from_room_label'] = $fromRoomLabel;
            $metadata['to_room_id'] = $exception->room_id;
            $metadata['to_room_label'] = $roomLabel;
        }

        return $this->publish(
            identity: [
                'source_type' => 'schedule_exception',
                'source_id' => $exception->id,
            ],
            data: [
                'title' => $title,
                'body' => $body,
                'type' => $noticeType,
                'room_id' => $exception->room_id,
                'metadata' => $metadata,
                'starts_at' => now(),
                'ends_at' => $this->endOfEventDay($exception->event_date),
                'is_pinned' => false,
            ],
            userId: $userId,
        );
    }

    public function announceRoomOverride(RoomOverride $override, ?int $userId = null): Notice
    {
        $override->loadMissing('room:id,code,name');

        $status = $this->enumString($override->status);
        $noticeType = $status === 'reserved' ? 'room_reserved' : 'room_maintenance';
        $roomLabel = $this->roomLabel($override->room);
        $scheduleLabel = $this->dateTimeRangeLabel($override->starts_at, $override->ends_at);

        [$title, $body] = match ($noticeType) {
            'room_reserved' => [
                "{$roomLabel} reserved for department activity",
                'This room is reserved for an official activity. Availability is blocked while this notice is active.',
            ],
            default => [
                "{$roomLabel} is under maintenance",
                'This room is temporarily unavailable. Normal schedules are overridden while this notice is active.',
            ],
        };

        return $this->publish(
            identity: [
                'source_type' => 'room_override',
                'source_id' => $override->id,
            ],
            data: [
                'title' => $title,
                'body' => $body,
                'type' => $noticeType,
                'room_id' => $override->room_id,
                'metadata' => [
                    'room_label' => $roomLabel,
                    'schedule_label' => $scheduleLabel,
                    'override_status' => $status,
                    'reason' => $override->reason,
                ],
                'starts_at' => now(),
                'ends_at' => $override->ends_at,
                'is_pinned' => false,
            ],
            userId: $userId,
        );
    }

    public function announceRoomDeactivated(Room $room, ?int $userId = null): Notice
    {
        $roomLabel = $this->roomLabel($room);

        return $this->publish(
            identity: [
                'source_type' => 'room',
                'source_id' => $room->id,
            ],
            data: [
                'title' => "{$roomLabel} is currently unavailable",
                'body' => 'This room has been deactivated in the room availability system. Please check other available rooms before proceeding.',
                'type' => 'general',
                'room_id' => $room->id,
                'metadata' => [
                    'room_label' => $roomLabel,
                    'room_status' => 'deactivated',
                ],
                'starts_at' => now(),
                'ends_at' => null,
                'is_pinned' => false,
            ],
            userId: $userId,
        );
    }

    public function deactivateForSource(string $sourceType, int $sourceId, ?int $userId = null): void
    {
        Notice::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'status' => 'archived',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);
    }

    public function deactivateRoomScopedNotices(int $roomId, ?int $userId = null): void
    {
        Notice::query()
            ->where('room_id', $roomId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('source_type')
                    ->orWhere('source_type', '!=', 'room');
            })
            ->update([
                'is_active' => false,
                'status' => 'archived',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);
    }

    private function publish(array $identity, array $data, ?int $userId = null): Notice
    {
        /** @var Notice|null $notice */
        $notice = Notice::withTrashed()
            ->where($identity)
            ->first();

        if (! $notice) {
            $notice = new Notice($identity);
            $notice->created_by = $userId;
        } elseif ($notice->trashed()) {
            $notice->restore();
        }

        $notice->fill([
            ...$data,
            'status' => 'published',
            'is_active' => true,
            'updated_by' => $userId,
        ]);

        if (! $notice->exists && $notice->created_by === null) {
            $notice->created_by = $userId;
        }

        $notice->save();

        return $notice->refresh();
    }

    private function exceptionNoticeType(string $eventType, ?string $action): string
    {
        if ($action === 'cancellation' || $eventType === 'cancellation') {
            return 'class_cancellation';
        }

        if ($action === 'room_change' || $eventType === 'room_change') {
            return 'room_change';
        }

        return 'special_class';
    }

    private function sectionSubjectLabel(?string $section, ?string $subjectTitle, ?string $subjectCode = null): string
    {
        $section = filled($section) ? trim((string) $section) : 'Selected class';
        $subject = filled($subjectTitle)
            ? trim((string) $subjectTitle)
            : (filled($subjectCode) ? trim((string) $subjectCode) : 'Class');

        return "{$section} - {$subject}";
    }

    private function roomLabel(?Room $room): ?string
    {
        if (! $room) {
            return null;
        }

        return $room->code ?: $room->name;
    }

    private function termLabel(AcademicTerm $term): string
    {
        if (isset($term->label) && filled($term->label)) {
            return (string) $term->label;
        }

        $yearStart = (int) $term->year_start;
        $yearEnd = $yearStart + 1;
        $semester = $this->enumString($term->semester);

        $semesterLabel = match ($semester) {
            '1', 'first', 'first_semester' => 'First Semester',
            '2', 'second', 'second_semester' => 'Second Semester',
            '3', 'summer', 'midyear' => 'Summer Term',
            default => Str::headline($semester),
        };

        return "A.Y. {$yearStart}-{$yearEnd}, {$semesterLabel}";
    }

    private function dayTimeLabel(mixed $dayOfWeek, mixed $startTime, mixed $endTime): ?string
    {
        $day = $this->enumString($dayOfWeek);
        $dayLabel = match ($day) {
            '1', 'monday' => 'Monday',
            '2', 'tuesday' => 'Tuesday',
            '3', 'wednesday' => 'Wednesday',
            '4', 'thursday' => 'Thursday',
            '5', 'friday' => 'Friday',
            '6', 'saturday' => 'Saturday',
            '7', 'sunday' => 'Sunday',
            default => Str::headline($day),
        };

        $timeLabel = $this->timeRangeLabel($startTime, $endTime);

        return trim("{$dayLabel} {$timeLabel}");
    }

    private function timeRangeLabel(mixed $startTime, mixed $endTime): ?string
    {
        if (! $startTime || ! $endTime) {
            return null;
        }

        return $this->timeLabel($startTime) . ' to ' . $this->timeLabel($endTime);
    }

    private function dateTimeRangeLabel(mixed $startsAt, mixed $endsAt): ?string
    {
        if (! $startsAt) {
            return null;
        }

        $start = $startsAt instanceof Carbon ? $startsAt : Carbon::parse($startsAt);

        if (! $endsAt) {
            return $start->format('M d, g:i A') . ' onwards';
        }

        $end = $endsAt instanceof Carbon ? $endsAt : Carbon::parse($endsAt);

        if ($start->isSameDay($end)) {
            return $start->format('M d, g:i A') . ' to ' . $end->format('g:i A');
        }

        return $start->format('M d, g:i A') . ' to ' . $end->format('M d, g:i A');
    }

    private function timeLabel(mixed $time): string
    {
        return Carbon::parse($time)->format('g:i A');
    }

    private function endOfEventDay(mixed $eventDate): Carbon
    {
        return Carbon::parse($eventDate)->endOfDay();
    }

    private function dateString(mixed $date): ?string
    {
        return $date ? Carbon::parse($date)->toDateString() : null;
    }

    private function enumString(mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        return (string) $value;
    }
}
