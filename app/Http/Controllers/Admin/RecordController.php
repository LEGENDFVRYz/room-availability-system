<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\RoomUsageLogService;
use App\Services\Support\ValueNormalizer;
use Inertia\Inertia;
use Inertia\Response;

class RecordController extends Controller
{
    public function __construct(
        private readonly RoomUsageLogService $roomUsageLogService,
        private readonly ValueNormalizer $normalizer,
    ) {}

    public function roomUsage(): Response
    {
        $rooms = Room::query()
            ->select('id', 'code', 'name', 'room_type', 'display_order')
            ->orderBy('display_order')
            ->orderBy('code')
            ->get()
            ->map(fn (Room $room) => [
                'id'        => $room->id,
                'code'      => $room->code,
                'name'      => $room->name,
                'room_type' => $this->normalizer->enumValue($room->room_type),
            ])
            ->values();

        return Inertia::render('Admin/Records/UsageLog', [
            'logs'  => $this->roomUsageLogService->roomUsageRecords(),
            'rooms' => $rooms,
        ]);
    }

    public function activity(): Response
    {
        return Inertia::render('Admin/Records/ActivityLog');
    }
}
