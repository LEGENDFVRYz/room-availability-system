<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoomRequest;
use App\Models\Room;
use App\Services\AcademicTermService;
use App\Services\RoomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ManageController extends Controller
{
    public function __construct(
        private readonly RoomService $roomService,
        private readonly AcademicTermService $academicTermService,
    ) {}

    // -------------------------------------------------------------------------
    //  Rooms
    // -------------------------------------------------------------------------

    public function rooms(): Response
    {
        $rooms = $this->roomService->getAll()
            ->map(fn(Room $r) => [
                'id'            => $r->id,
                'code'          => $r->code,
                'name'          => $r->name,
                'room_type'     => $r->room_type->value,
                'type_label'    => $r->room_type->label(),
                'floor'         => $r->floor,
                'capacity'      => $r->capacity,
                'display_order' => $r->display_order,
                'is_active'     => $r->is_active,
            ]);

        return Inertia::render('Admin/Manage/Rooms', [
            'rooms' => $rooms,
        ]);
    }

    public function storeRoom(RoomRequest $request): RedirectResponse
    {
        try {
            $data = array_merge($request->validated(), [
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->roomService->create($data);

            return redirect()->route('admin.manage.rooms')
                ->with('success', 'Room added successfully.');

        } catch (\Throwable $e) {
            Log::error('Failed to create room', ['error' => $e->getMessage()]);

            return redirect()->route('admin.manage.rooms')
                ->with('error', 'Failed to add the room. Please try again.');
        }
    }

    public function updateRoom(Room $room, RoomRequest $request): RedirectResponse
    {
        try {
            $data = array_merge($request->validated(), [
                'updated_by' => auth()->id(),
            ]);

            $this->roomService->update($room, $data);

            return redirect()->route('admin.manage.rooms')
                ->with('success', "Room \"{$room->name}\" updated successfully.");

        } catch (\Throwable $e) {
            Log::error('Failed to update room', ['room_id' => $room->id, 'error' => $e->getMessage()]);

            return redirect()->route('admin.manage.rooms')
                ->with('error', 'Failed to update the room. Please try again.');
        }
    }

    public function deleteRoom(Room $room): RedirectResponse
    {
        try {
            $this->roomService->deactivate($room, auth()->id());

            return redirect()->route('admin.manage.rooms')
                ->with('success', "Room \"{$room->name}\" has been deactivated.");

        } catch (\Throwable $e) {
            Log::error('Failed to deactivate room', ['room_id' => $room->id, 'error' => $e->getMessage()]);

            return redirect()->route('admin.manage.rooms')
                ->with('error', 'Failed to deactivate the room. Please try again.');
        }
    }

    // -------------------------------------------------------------------------
    //  Academic Configuration
    // -------------------------------------------------------------------------

    public function configs(): Response
    {
        // currentTerm is automatically injected by middleware
        return Inertia::render('Admin/Manage/Configs', [
            'configs' => [
                // System Polling
                'room_status_poll_interval'     => config('room-system.room_status_poll_interval', 30000),
                'kiosk_poll_interval'           => config('room-system.kiosk_poll_interval', 15000),
                'status_warning_minutes'        => config('room-system.status_warning_minutes', 15),

                // Daily Operations
                'claim_grace_minutes'           => config('daily_operations.claim_grace_minutes', 60),
                
                // Kiosk Configs
                'kiosk_display_name'            => config('kiosk.display_name', 'CPE Room Availability Board'),
                'kiosk_show_clock'              => config('kiosk.show_clock', true),
                'kiosk_notice_rotation_seconds' => config('kiosk.notice_rotation_seconds', 10),
            ]
        ]);
    }

    public function setCurrentTerm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year_start' => ['required', 'integer', 'min:2000', 'max:2099'],
            'semester'   => ['required', 'integer', 'in:1,2,3'],
        ]);

        try {
            $term = $this->academicTermService->findOrCreateAndSetCurrent(
                (int) $validated['year_start'],
                (int) $validated['semester'],
                auth()->id(),
            );

            return redirect()->route('admin.manage.configs')
                ->with('success', "Set \"{$term->label}\" as the active academic term.");

        } catch (\Throwable $e) {
            Log::error('Failed to set current academic term', ['error' => $e->getMessage()]);

            return redirect()->route('admin.manage.configs')
                ->with('error', 'Failed to update the active term. Please try again.');
        }
    }
}
