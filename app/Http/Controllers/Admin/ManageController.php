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
    // -------------------------------------------------------------------------
    //  Rooms
    // -------------------------------------------------------------------------

    public function rooms(): Response
    {
        $rooms = (new RoomService())->getAll()
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

            (new RoomService())->create($data);

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

            (new RoomService())->update($room, $data);

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
            (new RoomService())->deactivate($room);

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
        return Inertia::render('Admin/Manage/Configs');
    }

    public function setCurrentTerm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year_start' => ['required', 'integer', 'min:2000', 'max:2099'],
            'semester'   => ['required', 'integer', 'in:1,2,3'],
        ]);

        try {
            $term = (new AcademicTermService())->findOrCreateAndSetCurrent(
                (int) $validated['year_start'],
                (int) $validated['semester'],
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
