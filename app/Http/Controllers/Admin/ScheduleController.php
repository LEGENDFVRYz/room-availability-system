<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ScheduleRequest;
use App\Models\Room;
use App\Models\Schedule;
use App\Services\AcademicTermService;
use App\Services\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly AcademicTermService $academicTermService,
        private readonly ScheduleService $scheduleService,
    ) {}

    // Shared helpers 
    private function activeRooms(): \Illuminate\Support\Collection
    {
        return Room::where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Room $r) => [
                'id'   => $r->id,
                'name' => $r->name,
                'code' => $r->code,
            ]);
    }

    // Pages 
    public function sections(): Response
    {
        $term = $this->academicTermService->getCurrent();

        return Inertia::render('Admin/Schedule/Sections', [
            'sections' => $term ? $this->scheduleService->getSectionsForTerm($term) : [],
            'rooms'    => $this->activeRooms(),
        ]);
    }

    public function rooms(): Response
    {
        $term = $this->academicTermService->getCurrent();

        return Inertia::render('Admin/Schedule/Rooms', [
            'room_schedules' => $term ? $this->scheduleService->getRoomSchedulesForTerm($term) : [],
            'rooms'          => $this->activeRooms(),
        ]);
    }

    // CRUD Operations
    public function store(ScheduleRequest $request): RedirectResponse
    {
        $term = $this->academicTermService->getCurrent();

        if (! $term) {
            return redirect()->route('admin.schedules.sections')
                ->with('error', 'No active academic term. Set one in Manage → Config first.');
        }

        try {
            $result = $this->scheduleService->create(
                $request->validated(),
                $term->id,
                auth()->id(),
            );

            if ($result['conflict']) {
                return redirect()->route('admin.schedules.sections')
                    ->with('error', 'Schedule conflict: that room is already booked during that time.');
            }

            return redirect()->route('admin.schedules.sections')
                ->with('success', 'Schedule added successfully.');

        } catch (\Throwable $e) {
            Log::error('Failed to create schedule', ['error' => $e->getMessage()]);

            return redirect()->route('admin.schedules.sections')
                ->with('error', 'Failed to add the schedule. Please try again.');
        }
    }

    public function update(Schedule $schedule, ScheduleRequest $request): RedirectResponse
    {
        try {
            $result = $this->scheduleService->update(
                $schedule,
                $request->validated(),
                auth()->id(),
            );

            if ($result['conflict']) {
                return redirect()->route('admin.schedules.sections')
                    ->with('error', 'Schedule conflict: that room is already booked during that time.');
            }

            return redirect()->route('admin.schedules.sections')
                ->with('success', 'Schedule updated successfully.');

        } catch (\Throwable $e) {
            Log::error('Failed to update schedule', ['schedule_id' => $schedule->id, 'error' => $e->getMessage()]);

            return redirect()->route('admin.schedules.sections')
                ->with('error', 'Failed to update the schedule. Please try again.');
        }
    }

    public function destroy(Schedule $schedule): RedirectResponse
    {
        try {
            $this->scheduleService->delete($schedule, auth()->id());

            return redirect()->route('admin.schedules.sections')
                ->with('success', 'Schedule entry deleted.');

        } catch (\Throwable $e) {
            Log::error('Failed to delete schedule', ['schedule_id' => $schedule->id, 'error' => $e->getMessage()]);

            return redirect()->route('admin.schedules.sections')
                ->with('error', 'Failed to delete the schedule. Please try again.');
        }
    }
}
