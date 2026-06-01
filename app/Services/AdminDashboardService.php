<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Notice;
use App\Models\Room;
use App\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminDashboardService
{
    public function __construct(
        private readonly AcademicTermService $academicTermService,
        private readonly DailyOperationReadService $dailyOperationReadService,
    ) {}

    public function payload(): array
    {
        $today = now()->toDateString();
        $term = $this->academicTermService->getCurrentOrActive($today);

        $dailyPayload = $this->dailyOperationReadService->payloadForDate($today);

        return [
            'termOverview'  => $this->termOverview($term),
            'roomStats'     => $dailyPayload['room_stats'] ?? $this->dailyOperationReadService->roomStatsFromPayload($dailyPayload),
            'utilization'   => $this->utilizationInsights($term),
            'scheduleHealth'=> $this->scheduleHealth($term),
            'activityFeed'  => $this->activityFeed(),
            'quickActions'  => $this->quickActions(),
        ];
    }

    private function termOverview(?AcademicTerm $term): array
    {
        $activeRoomsCount = Room::query()
            ->where('is_active', true)
            ->count();

        $schedulesCount = $term
            ? Schedule::query()
                ->where('academic_term_id', $term->id)
                ->where('is_active', true)
                ->count()
            : 0;

        $facultyCount = $term
            ? Schedule::query()
                ->where('academic_term_id', $term->id)
                ->where('is_active', true)
                ->whereNotNull('instructor_name')
                ->where('instructor_name', '!=', '')
                ->distinct()
                ->count('instructor_name')
            : 0;

        return [
            'id'              => $term?->id,
            'school_year'     => $term ? 'SY ' . $term->school_year_label : 'No Academic Term',
            'semester'        => $term ? $this->semesterLabel($term->semester) : 'Not Set',
            'is_active'       => (bool) ($term?->is_active ?? false),
            'rooms_count'     => $activeRoomsCount,
            'schedules_count' => $schedulesCount,
            'faculty_count'   => $facultyCount,
        ];
    }

    private function utilizationInsights(?AcademicTerm $term): array
    {
        if (! $term) {
            return $this->emptyUtilization();
        }

        $rooms = Room::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('code')
            ->get(['id', 'code']);

        $schedules = Schedule::query()
            ->with('room:id,code')
            ->where('academic_term_id', $term->id)
            ->where('is_active', true)
            ->get();

        if ($rooms->isEmpty() || $schedules->isEmpty()) {
            return $this->emptyUtilization();
        }

        $roomMinutes = $rooms->mapWithKeys(fn (Room $room) => [$room->id => 0])->all();
        $dayWindows = [];

        foreach ($schedules as $schedule) {
            $start = $this->minutes($schedule->start_time);
            $end = $this->minutes($schedule->end_time);

            if ($start === null || $end === null || $start >= $end || ! $schedule->room_id) {
                continue;
            }

            $roomMinutes[$schedule->room_id] = ($roomMinutes[$schedule->room_id] ?? 0) + ($end - $start);

            $day = $this->enumValue($schedule->day_of_week);
            $dayWindows[$day]['min'] = min($dayWindows[$day]['min'] ?? $start, $start);
            $dayWindows[$day]['max'] = max($dayWindows[$day]['max'] ?? $end, $end);
        }

        $possibleWeeklyMinutes = collect($dayWindows)
            ->sum(fn (array $window) => max(0, ($window['max'] ?? 0) - ($window['min'] ?? 0)));

        $possibleWeeklyMinutes = max(1, (int) $possibleWeeklyMinutes);

        $usageRows = $rooms->map(function (Room $room) use ($roomMinutes, $possibleWeeklyMinutes) {
            $minutes = (int) ($roomMinutes[$room->id] ?? 0);

            return [
                'room_id'    => $room->id,
                'room_code'  => $room->code,
                'minutes'    => $minutes,
                'percentage' => min(100, (int) round(($minutes / $possibleWeeklyMinutes) * 100)),
            ];
        });

        $mostUsed = $usageRows->sortByDesc('minutes')->first();
        $leastUsed = $usageRows->sortBy('minutes')->first();

        return [
            'most_used' => [
                'room_code'  => $mostUsed['room_code'] ?? '—',
                'percentage' => $mostUsed['percentage'] ?? 0,
            ],
            'least_used' => [
                'room_code'  => $leastUsed['room_code'] ?? '—',
                'percentage' => $leastUsed['percentage'] ?? 0,
            ],
            'peak_hours' => $this->peakHours($schedules),
        ];
    }

    private function scheduleHealth(?AcademicTerm $term): array
    {
        if (! $term) {
            return [
                ['label' => 'Room Conflicts',    'value' => 0, 'status' => 'good'],
                ['label' => 'Invalid Schedules', 'value' => 0, 'status' => 'good'],
                ['label' => 'Unassigned Rooms',  'value' => 0, 'status' => 'good'],
                ['label' => 'Duplicate Entries', 'value' => 0, 'status' => 'good'],
            ];
        }

        $schedules = Schedule::query()
            ->with('room:id,is_active')
            ->where('academic_term_id', $term->id)
            ->where('is_active', true)
            ->get();

        $conflicts = $this->countRoomConflicts($schedules);

        $invalidSchedules = $schedules
            ->filter(fn (Schedule $schedule) => $this->isInvalidSchedule($schedule))
            ->count();

        $unassignedRooms = Room::query()
            ->where('is_active', true)
            ->whereDoesntHave('schedules', function ($query) use ($term) {
                $query->where('academic_term_id', $term->id)
                    ->where('is_active', true);
            })
            ->count();

        $duplicateEntries = Schedule::query()
            ->select('room_id', 'day_of_week', 'start_time', 'end_time', 'subject_code', 'section', DB::raw('COUNT(*) as total'))
            ->where('academic_term_id', $term->id)
            ->where('is_active', true)
            ->groupBy('room_id', 'day_of_week', 'start_time', 'end_time', 'subject_code', 'section')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->sum(fn ($row) => max(0, ((int) $row->total) - 1));

        return [
            ['label' => 'Room Conflicts',    'value' => $conflicts,          'status' => $conflicts > 0 ? 'warning' : 'good'],
            ['label' => 'Invalid Schedules', 'value' => $invalidSchedules,   'status' => $invalidSchedules > 0 ? 'warning' : 'good'],
            ['label' => 'Unassigned Rooms',  'value' => $unassignedRooms,    'status' => $unassignedRooms > 0 ? 'warning' : 'good'],
            ['label' => 'Duplicate Entries', 'value' => $duplicateEntries,   'status' => $duplicateEntries > 0 ? 'warning' : 'good'],
        ];
    }

    private function activityFeed(): array
    {
        return Notice::query()
            ->latest('updated_at')
            ->latest('id')
            ->limit(4)
            ->get(['id', 'title', 'type', 'updated_at', 'created_at'])
            ->map(function (Notice $notice) {
                return [
                    'time'   => ($notice->updated_at ?? $notice->created_at)?->format('g:i A') ?? '',
                    'action' => $notice->title,
                    'type'   => $this->activityType($notice->type),
                ];
            })
            ->values()
            ->all();
    }

    private function quickActions(): array
    {
        return [
            ['label' => 'Add Schedule',    'href' => url('/admin/schedules')],
            ['label' => 'View Operations', 'href' => url('/admin/operations/daily')],
            ['label' => 'Add Room',        'href' => url('/admin/manage/rooms')],
            ['label' => 'View Records',    'href' => url('/admin/records/usage')],
        ];
    }

    private function emptyUtilization(): array
    {
        return [
            'most_used'  => ['room_code' => '—', 'percentage' => 0],
            'least_used' => ['room_code' => '—', 'percentage' => 0],
            'peak_hours' => 'No schedule data',
        ];
    }

    private function countRoomConflicts(Collection $schedules): int
    {
        $count = 0;

        $groups = $schedules
            ->filter(fn (Schedule $schedule) => $schedule->room_id && $schedule->day_of_week)
            ->groupBy(fn (Schedule $schedule) => $schedule->room_id . '-' . $this->enumValue($schedule->day_of_week));

        foreach ($groups as $group) {
            $items = $group
                ->sortBy(fn (Schedule $schedule) => $this->minutes($schedule->start_time) ?? 0)
                ->values();

            for ($i = 0; $i < $items->count(); $i++) {
                $current = $items[$i];
                $currentStart = $this->minutes($current->start_time);
                $currentEnd = $this->minutes($current->end_time);

                if ($currentStart === null || $currentEnd === null || $currentStart >= $currentEnd) {
                    continue;
                }

                for ($j = $i + 1; $j < $items->count(); $j++) {
                    $next = $items[$j];
                    $nextStart = $this->minutes($next->start_time);
                    $nextEnd = $this->minutes($next->end_time);

                    if ($nextStart === null || $nextEnd === null || $nextStart >= $nextEnd) {
                        continue;
                    }

                    if ($nextStart >= $currentEnd) {
                        break;
                    }

                    if ($currentStart < $nextEnd && $currentEnd > $nextStart) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    private function isInvalidSchedule(Schedule $schedule): bool
    {
        $start = $this->minutes($schedule->start_time);
        $end = $this->minutes($schedule->end_time);

        return ! $schedule->room_id
            || ! $schedule->room
            || ! (bool) $schedule->room->is_active
            || $this->blank($schedule->subject_code)
            || $this->blank($schedule->section)
            || $start === null
            || $end === null
            || $start >= $end;
    }

    private function peakHours(Collection $schedules): string
    {
        $slotCounts = [];

        foreach ($schedules as $schedule) {
            $start = $this->minutes($schedule->start_time);
            $end = $this->minutes($schedule->end_time);

            if ($start === null || $end === null || $start >= $end) {
                continue;
            }

            $startSlot = (int) (floor($start / 30) * 30);
            $endSlot = (int) (ceil($end / 30) * 30);

            for ($slot = $startSlot; $slot < $endSlot && $slot < 1440; $slot += 30) {
                $slotCounts[$slot] = ($slotCounts[$slot] ?? 0) + 1;
            }
        }

        if ($slotCounts === []) {
            return 'No schedule data';
        }

        $max = max($slotCounts);
        $topSlots = collect($slotCounts)
            ->filter(fn (int $count) => $count === $max)
            ->keys()
            ->map(fn ($slot) => (int) $slot)
            ->sort()
            ->values();

        $bestStart = $topSlots->first();
        $bestEnd = $bestStart + 30;
        $currentStart = $bestStart;
        $currentEnd = $bestEnd;

        for ($i = 1; $i < $topSlots->count(); $i++) {
            $slot = $topSlots[$i];

            if ($slot === $currentEnd) {
                $currentEnd += 30;
            } else {
                if (($currentEnd - $currentStart) > ($bestEnd - $bestStart)) {
                    $bestStart = $currentStart;
                    $bestEnd = $currentEnd;
                }

                $currentStart = $slot;
                $currentEnd = $slot + 30;
            }
        }

        if (($currentEnd - $currentStart) > ($bestEnd - $bestStart)) {
            $bestStart = $currentStart;
            $bestEnd = $currentEnd;
        }

        return $this->formatMinutes($bestStart) . ' – ' . $this->formatMinutes($bestEnd);
    }

    private function activityType(mixed $noticeType): string
    {
        $type = (string) $this->enumValue($noticeType);

        return match ($type) {
            'room_reserved'      => 'reserved',
            'room_maintenance'   => 'maintenance',
            'class_cancellation' => 'cancelled',
            'room_change'        => 'change',
            'special_class',
            'makeup_class'       => 'special',
            'schedule_update'    => 'schedule_update',
            'academic_term'      => 'academic_term',
            default              => 'notice',
        };
    }

    private function semesterLabel(mixed $semester): string
    {
        if (is_object($semester) && method_exists($semester, 'label')) {
            return $semester->label();
        }

        $value = (string) $this->enumValue($semester);

        return match ($value) {
            '1', 'first', 'first_semester' => '1st Semester',
            '2', 'second', 'second_semester' => '2nd Semester',
            'summer' => 'Summer',
            default => Str::headline(str_replace('_', ' ', $value)),
        };
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
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

    private function formatMinutes(int $minutes): string
    {
        $minutes = max(0, min(1440, $minutes));

        return Carbon::createFromTime(0, 0)->addMinutes($minutes)->format('g:i A');
    }

    private function blank(mixed $value): bool
    {
        return trim((string) ($value ?? '')) === '';
    }
}
