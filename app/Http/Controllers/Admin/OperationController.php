<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoomOverrideRequest;
use App\Http\Requests\Admin\UpdateRoomOverrideRequest;
use App\Models\Room;
use App\Models\RoomOverride;
use App\Models\ScheduleException;
use App\Services\DailyOperationReadService;
use App\Services\DailyOperationService;
use App\Services\RoomOverrideService;
use App\Services\Support\ValueNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OperationController extends Controller
{
    public function __construct(
        private readonly RoomOverrideService $roomOverrideService,
        private readonly DailyOperationService $dailyOperationService,
        private readonly DailyOperationReadService $dailyOperationReadService,
        private readonly ValueNormalizer $normalizer,
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

        return Inertia::render(
            'Admin/Operations/Daily',
            $this->dailyOperationReadService->payloadForDate($selectedDateInput)
        );
    }

    public function requestClass(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_date'      => ['required', 'date'],
            'event_type'      => ['required', Rule::in(['special_class', 'makeup_class'])],
            'room_id'         => ['required', 'integer', 'exists:tbl_rooms,id'],
            'subject_code'    => ['required', 'string', 'max:20'],
            'subject_title'   => ['required', 'string', 'max:150'],
            'section'         => ['required', 'string', 'max:30'],
            'instructor_name' => ['nullable', 'string', 'max:100'],
            'start_time'      => ['required', 'date_format:H:i'],
            'end_time'        => ['required', 'date_format:H:i', 'after:start_time'],
            'reason'          => ['nullable', 'string'],
        ]);

        $this->dailyOperationService->requestClass($validated, $request->user()->id);

        return back()->with('success', 'Class request created successfully.');
    }

    public function cancelClass(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_date'   => ['required', 'date'],
            'schedule_id'  => ['nullable', 'integer', 'exists:tbl_schedules,id'],
            'exception_id' => ['nullable', 'integer', 'exists:tbl_schedule_exceptions,id'],
            'reason'       => ['nullable', 'string'],
        ]);

        $this->dailyOperationService->cancelClass($validated, $request->user()->id);

        return back()->with('success', 'Class cancelled for the selected date.');
    }

    public function changeRoom(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_date'   => ['required', 'date'],
            'schedule_id'  => ['nullable', 'integer', 'exists:tbl_schedules,id'],
            'exception_id' => ['nullable', 'integer', 'exists:tbl_schedule_exceptions,id'],
            'room_id'      => ['required', 'integer', 'exists:tbl_rooms,id'],
            'reason'       => ['nullable', 'string'],
        ]);

        $this->dailyOperationService->changeRoom($validated, $request->user()->id);

        return back()->with('success', 'Room changed for the selected date.');
    }

    public function markStarted(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_date'   => ['required', 'date'],
            'schedule_id'  => ['nullable', 'integer', 'exists:tbl_schedules,id'],
            'exception_id' => ['nullable', 'integer', 'exists:tbl_schedule_exceptions,id'],
        ]);

        $this->dailyOperationService->markStarted($validated, $request->user()->id);

        return back()->with('success', 'Class marked as started.');
    }

    public function markCompleted(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_date'   => ['required', 'date'],
            'schedule_id'  => ['nullable', 'integer', 'exists:tbl_schedules,id'],
            'exception_id' => ['nullable', 'integer', 'exists:tbl_schedule_exceptions,id'],
        ]);

        $this->dailyOperationService->markCompleted($validated, $request->user()->id);

        return back()->with('success', 'Class marked as completed.');
    }

    public function revertStarted(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_date'   => ['required', 'date'],
            'schedule_id'  => ['nullable', 'integer', 'exists:tbl_schedules,id'],
            'exception_id' => ['nullable', 'integer', 'exists:tbl_schedule_exceptions,id'],
        ]);

        $this->dailyOperationService->revertStarted($validated, $request->user()->id);

        return back()->with('success', 'Class start reverted and usage log reset.');
    }

    public function revertCompleted(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_date'   => ['required', 'date'],
            'schedule_id'  => ['nullable', 'integer', 'exists:tbl_schedules,id'],
            'exception_id' => ['nullable', 'integer', 'exists:tbl_schedule_exceptions,id'],
        ]);

        $this->dailyOperationService->revertCompleted($validated, $request->user()->id);

        return back()->with('success', 'Class completion reverted and usage log reopened.');
    }

    public function revertCancellation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_date'   => ['required', 'date'],
            'schedule_id'  => ['nullable', 'integer', 'exists:tbl_schedules,id'],
            'exception_id' => ['nullable', 'integer', 'exists:tbl_schedule_exceptions,id'],
        ]);

        $this->dailyOperationService->revertCancellation($validated, $request->user()->id);

        return back()->with('success', 'Class cancellation reverted and usage log reset.');
    }

    public function destroyException(ScheduleException $scheduleException): RedirectResponse
    {
        $scheduleException->delete();

        return back()->with('success', 'Daily exception removed.');
    }

    # -------------------------------------------------------------------
    # Room Status Controls
    # -------------------------------------------------------------------
    public function rooms(): Response
    {
        $rooms = Room::select('id', 'code', 'name', 'room_type')->get();

        $overrides = RoomOverride::with(['room', 'createdBy', 'updatedBy'])
            ->orderBy('starts_at', 'desc')
            ->get()
            ->map(function (RoomOverride $override) {
                return [
                    'id'              => $override->id,
                    'room_id'         => $override->room_id,
                    'room_code'       => $override->room->code ?? 'UNKNOWN',
                    'room_name'       => $override->room->name ?? 'Unknown Room',
                    'status'          => $this->normalizer->enumValue($override->status),
                    'reason'          => $override->reason,
                    'starts_at'       => $override->starts_at->toIso8601String(),
                    'ends_at'         => $override->ends_at?->toIso8601String(),
                    'is_active'       => $override->is_active,
                    'created_by_name' => $override->createdBy->name ?? 'System',
                    'updated_by_name' => $override->updatedBy->name ?? null,
                ];
            });

        return Inertia::render('Admin/Operations/RoomStatus', [
            'rooms'     => $rooms,
            'overrides' => $overrides,
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
}
