<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\Room;
use App\Models\RoomOverride;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationController extends Controller
{
    # -------------------------------------------------------------------
    # "Daily Operation" Controls
    # -------------------------------------------------------------------
    public function daily(): Response
    {
        return Inertia::render('Admin/Operations/Daily');
    }


    # -------------------------------------------------------------------
    # "Room Status" Controls
    # -------------------------------------------------------------------
    public function rooms(): Response
    {
        $rooms = Room::select('id', 'code', 'name', 'room_type')->get();

        // Fetch overrides and map them to the RoomOverrideItem interface
        $overrides = RoomOverride::with(['room', 'createdBy', 'updatedBy'])
            ->orderBy('starts_at', 'desc')
            ->get()
            ->map(function ($override) {
                return [
                    'id'              => $override->id,
                    'room_id'         => $override->room_id,
                    'room_code'       => $override->room->code ?? 'UNKNOWN',
                    'room_name'       => $override->room->name ?? 'Unknown Room',
                    // Extract the enum value for the frontend string literal type
                    'status'          => $override->status->value,
                    'reason'          => $override->reason,
                    // Format dates safely for JavaScript's Date parser
                    'starts_at'       => $override->starts_at->toIso8601String(),
                    'ends_at'         => $override->ends_at?->toIso8601String(),
                    'is_active'       => $override->is_active,
                    'created_by_name' => $override->createdBy->name ?? 'System',
                    'updated_by_name' => $override->updatedBy->name ?? null,
                ];
            });
        
        return Inertia::render('Admin/Operations/RoomStatus', [
            'currentTerm' => AcademicTerm::where('is_current', true)->first(), 
            'rooms'       => $rooms,
            'overrides'   => $overrides,
        ]);
    }
}
