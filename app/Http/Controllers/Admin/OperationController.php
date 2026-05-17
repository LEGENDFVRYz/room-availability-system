<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoomOverrideRequest;
use App\Http\Requests\Admin\UpdateRoomOverrideRequest;
use App\Models\AcademicTerm;
use App\Models\Room;
use App\Models\RoomOverride;
use App\Services\RoomOverrideService;
use BackedEnum;
use Illuminate\Http\RedirectResponse;
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
    public function daily(): Response
    {
        return Inertia::render('Admin/Operations/Daily');
    }



    # -------------------------------------------------------------------
    # Room Status / Room Override Operations
    # -------------------------------------------------------------------
    public function rooms(): Response
    {
        return Inertia::render('Admin/Operations/RoomStatus', [
            'rooms'       => $this->roomOptions(),
            'overrides'   => $this->roomOverrideItems(),
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

    # ------ HELPERS ------
    private function roomOptions()
    {
        // Dropdown Purpose
        return Room::query()
            ->select('id', 'code', 'name', 'room_type', 'display_order', 'is_active')
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('code')
            ->get()
            ->map(fn (Room $room) => [
                'id'        => $room->id,
                'code'      => $room->code,
                'name'      => $room->name,
                'room_type' => $this->enumValue($room->room_type),
            ]);
    }

    private function roomOverrideItems()
    {
        // Actual Table Content
        return RoomOverride::query()
            ->with([
                'room:id,code,name,room_type',
                'createdBy:id,name',
                'updatedBy:id,name',
            ])
            ->latest('starts_at')
            ->get()
            ->map(fn (RoomOverride $override) => [
                'id'              => $override->id,
                'room_id'         => $override->room_id,
                'room_code'       => $override->room?->code ?? 'UNKNOWN',
                'room_name'       => $override->room?->name ?? 'Unknown Room',
                'status'          => $this->enumValue($override->status),
                'reason'          => $override->reason,
                'starts_at'       => $override->starts_at?->toIso8601String(),
                'ends_at'         => $override->ends_at?->toIso8601String(),
                'is_active'       => (bool) $override->is_active,
                'created_by_name' => $override->createdBy?->name ?? 'System',
                'updated_by_name' => $override->updatedBy?->name,
            ]);
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
