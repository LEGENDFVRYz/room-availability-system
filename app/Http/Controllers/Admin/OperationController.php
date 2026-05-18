<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoomOverrideRequest;
use App\Http\Requests\Admin\UpdateRoomOverrideRequest;
use App\Services\RoomOverrideService;
use App\Models\AcademicTerm;
use App\Models\Room;
use App\Models\RoomOverride;
use App\Models\Schedule;
use App\Models\ScheduleException;
use BackedEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class OperationController extends Controller
{
    public function __construct(
        private readonly RoomOverrideService $roomOverrideService,
    ) {}

    # -------------------------------------------------------------------
    # Daily Schedule Operations
    # -------------------------------------------------------------------
    public function daily(Request $request): Response
    {
        $selectedDateInput = $request->query(
            'selected_date',
            $request->query('date', now()->toDateString())
        );

        try {
            $date = Carbon::parse($selectedDateInput)->startOfDay();
        } catch (\Throwable) {
            $date = now()->startOfDay();
        }

        $selectedDate = $date->toDateString();
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();

        // Prefer the term that actually contains the selected date.
        // This fixes cases where the global current term and the selected date's term are out of sync.
        $operationTerm = $this->resolveOperationTerm($date);

        $roomsBaseQuery = Room::query()
            ->select('id', 'code', 'name', 'room_type', 'display_order', 'is_active')
            ->orderBy('display_order')
            ->orderBy('code');

        // Layer 1: room overrides are not term-based.
        $roomOverrides = RoomOverride::query()
            ->enabled()
            ->with('room:id,code,name,room_type')
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
            // Layer 2: date-specific exceptions.
            // Includes schedule_id = NULL records: special_class and makeup_class.
            $exceptions = $this->scheduleExceptionsForDate($selectedDate, $operationTerm->id);

            $scheduleIdsWithReplacingExceptions = $exceptions
                ->filter(function (ScheduleException $exception) {
                    if (! $exception->schedule_id) {
                        return false;
                    }

                    $eventType = $this->enumValue($exception->event_type);
                    $status = $this->enumValue($exception->status);

                    return in_array($eventType, ['cancellation', 'room_change'], true)
                        && $status !== 'auto_cancelled';
                })
                ->pluck('schedule_id')
                ->unique()
                ->values();

            // Layer 3: baseline weekly schedules for the selected date's weekday.
            $regularSchedules = Schedule::query()
                ->active()
                ->with('room:id,code,name,room_type')
                ->where('academic_term_id', $operationTerm->id)
                ->where('day_of_week', $date->dayOfWeekIso)
                ->when($scheduleIdsWithReplacingExceptions->isNotEmpty(), function ($query) use ($scheduleIdsWithReplacingExceptions) {
                    $query->whereNotIn('id', $scheduleIdsWithReplacingExceptions);
                })
                ->orderBy('start_time')
                ->get();

            // Defensive fallback for imported/seeded one-off exceptions whose term flag is out of sync.
            // This is intentionally limited to selected-date records with schedule_id NULL or linked to today's baseline schedules.
            if ($exceptions->isEmpty()) {
                $baselineScheduleIds = $regularSchedules->pluck('id')->filter()->values();

                $exceptions = ScheduleException::query()
                    ->with([
                        'room:id,code,name,room_type',
                        'schedule:id,room_id,subject_code,subject_title,section,instructor_name,start_time,end_time',
                        'schedule.room:id,code,name,room_type',
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
                'id'   => $room->id,
                'code' => $room->code,
                'name' => $room->name,
                'type' => $this->enumValue($room->room_type),
            ])
            ->values();

        $regularItems = $regularSchedules->map(fn (Schedule $schedule) => [
            'id'                 => "schedule-{$schedule->id}",
            'schedule_id'        => $schedule->id,
            'exception_id'       => null,
            'override_id'        => null,
            'room_id'            => $schedule->room_id,
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
            'start_time'         => $this->timeValue($schedule->start_time),
            'end_time'           => $this->timeValue($schedule->end_time),
            'reason'             => null,
        ]);

        $exceptionItems = $exceptions->map(function (ScheduleException $exception) use ($selectedDate) {
            $schedule = $exception->schedule;
            $eventType = $this->enumValue($exception->event_type);
            $status = $this->enumValue($exception->status);
            $originalRoom = $schedule?->room;

            if ($eventType === 'cancellation') {
                $status = 'cancelled';
            }

            return [
                'id'                 => "exception-{$exception->id}",
                'schedule_id'        => $exception->schedule_id,
                'exception_id'       => $exception->id,
                'override_id'        => null,
                'room_id'            => $exception->room_id,
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
                'start_time'         => $this->timeValue($exception->start_time ?: $schedule?->start_time),
                'end_time'           => $this->timeValue($exception->end_time ?: $schedule?->end_time),
                'reason'             => $exception->reason,
            ];
        });

        $overrideItems = $roomOverrides->map(function (RoomOverride $override) use ($dayStart, $dayEnd, $selectedDate) {
            $status = $this->enumValue($override->status);

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
                'override_id'        => $override->id,
                'room_id'            => $override->room_id,
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
                'start_time'         => $this->dateTimeToTime($effectiveStart, $dayStart),
                'end_time'           => $this->dateTimeToTime($effectiveEnd, $dayEnd),
                'reason'             => $override->reason,
                'starts_at'          => $override->starts_at?->toIso8601String(),
                'ends_at'            => $override->ends_at?->toIso8601String(),
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

        // dd($exceptionItems);

        return Inertia::render('Admin/Operations/Daily', [
            'rooms'             => $rooms,
            'daily_schedules'   => $dailySchedules,
            'selected_date'     => $selectedDate,
            'operation_term_id' => $operationTerm?->id,
        ]);
    }



    # -------------------------------------------------------------------
    # "Room Status" Controls
    # -------------------------------------------------------------------
    public function rooms(): Response
    {
        $rooms = Room::select('id', 'code', 'name', 'room_type')->get();

        $overrides = RoomOverride::with(['room', 'createdBy', 'updatedBy'])
            ->orderBy('starts_at', 'desc')
            ->get()
            ->map(function ($override) {
                return [
                    'id'              => $override->id,
                    'room_id'         => $override->room_id,
                    'room_code'       => $override->room->code ?? 'UNKNOWN',
                    'room_name'       => $override->room->name ?? 'Unknown Room',
                    'status'          => $this->enumValue($override->status),
                    'reason'          => $override->reason,
                    'starts_at'       => $override->starts_at->toIso8601String(),
                    'ends_at'         => $override->ends_at?->toIso8601String(),
                    'is_active'       => $override->is_active,
                    'created_by_name' => $override->createdBy->name ?? 'System',
                    'updated_by_name' => $override->updatedBy->name ?? null,
                ];
            });

        return Inertia::render('Admin/Operations/RoomStatus', [
            'rooms'       => $rooms,
            'overrides'   => $overrides,
        ]);
    }

    public function storeRoomOverride(StoreRoomOverrideRequest $request): RedirectResponse
    {
        $this->roomOverrideService->create(
            data: $request->validated(),
            userId: $request->user()->id,
        );

        return back()->with('success', 'Room override created successfully.');
    }

    public function updateRoomOverride(UpdateRoomOverrideRequest $request, RoomOverride $roomOverride): RedirectResponse
    {
        $this->roomOverrideService->update(
            override: $roomOverride,
            data: $request->validated(),
            userId: $request->user()->id,
        );

        return back()->with('success', 'Room override updated successfully.');
    }

    public function clearRoomOverride(RoomOverride $roomOverride): RedirectResponse
    {
        $this->roomOverrideService->clear(
            override: $roomOverride,
            userId: request()->user()->id,
        );

        return back()->with('success', 'Room override cleared successfully.');
    }



    # -------------------------------------------------------------------
    #  HELPERS 
    # -------------------------------------------------------------------
    
    # --- Shared ----
    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
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

    private function dateTimeToTime(?Carbon $value, Carbon $fallback): string
    {
        return ($value ?? $fallback)->format('H:i');
    }

    # --- Daily Operations ----
    private function resolveOperationTerm(Carbon $date): ?AcademicTerm
    {
        $selectedDate = $date->toDateString();

        return AcademicTerm::query()
            ->where(function ($query) use ($selectedDate) {
                $query->whereNull('starts_on')
                    ->orWhereDate('starts_on', '<=', $selectedDate);
            })
            ->where(function ($query) use ($selectedDate) {
                $query->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $selectedDate);
            })
            ->orderByDesc('is_current')
            ->orderByDesc('is_active')
            ->latest('id')
            ->first()
            ?? AcademicTerm::current()->first();
    }

    private function scheduleExceptionsForDate(string $selectedDate, int $academicTermId)
    {
        return ScheduleException::query()
            ->with([
                'room:id,code,name,room_type',
                'schedule:id,room_id,subject_code,subject_title,section,instructor_name,start_time,end_time',
                'schedule.room:id,code,name,room_type',
            ])
            ->where('academic_term_id', $academicTermId)
            ->whereDate('event_date', $selectedDate)
            ->orderBy('start_time')
            ->get();
    }
}
