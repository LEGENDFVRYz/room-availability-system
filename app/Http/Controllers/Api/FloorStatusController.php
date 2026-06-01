<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\DailyOperationReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FloorStatusController extends Controller
{
    public function __construct(
        private readonly DailyOperationReadService $dailyOperationReadService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());
        $floor = $request->integer('floor', 3);
        $nowTime = now()->format('H:i');

        $operationPayload = $this->dailyOperationReadService->payloadForDate($date);
        $dailySchedules = collect($operationPayload['daily_schedules'] ?? []);

        $activeRooms = Room::query()
            ->where('is_active', true)
            ->where('floor', $floor)
            ->orderBy('display_order')
            ->orderBy('code')
            ->get()
            ->keyBy('id');

        $currentItemsByRoom = $this->currentItemsByRoom($dailySchedules, $nowTime);

        $data = $activeRooms
            ->map(function (Room $room) use ($currentItemsByRoom) {
                $item = $currentItemsByRoom->get($room->id);
                $status = $item ? $this->toFloorplanStatus($item) : 'available';

                return [
                    // Important: this must match floorplanRooms[].id.
                    'room_id'       => $room->code,
                    'code'          => $room->code,
                    'label'         => $room->name,
                    'room_type'     => $this->enumValue($room->room_type),
                    'floor'         => $room->floor,
                    'capacity'      => $room->capacity,
                    'status'        => $status,

                    // Daily Operations context for panel/tooltips if needed later.
                    'daily_item_id' => $item['id'] ?? null,
                    'raw_status'    => $item['status'] ?? null,
                    'source'        => $item['source'] ?? null,
                    'event_type'    => $item['event_type'] ?? null,
                    'subject_code'  => $item['subject_code'] ?? null,
                    'subject'       => $item['subject_title'] ?? null,
                    'section'       => $item['section'] ?? null,
                    'teacher'       => $item['instructor_name'] ?? null,
                    'starts_at'     => $item['start_time'] ?? null,
                    'ends_at'       => $item['end_time'] ?? null,
                    'reason'        => $item['reason'] ?? null,
                ];
            })
            ->values();

        return response()->json($data);
    }

    private function currentItemsByRoom(Collection $dailySchedules, string $nowTime): Collection
    {
        return $dailySchedules
            ->filter(fn (array $item) => $this->isCurrentItem($item, $nowTime))
            ->map(function (array $item) {
                $item['_priority'] = $this->priority($item);
                return $item;
            })
            ->sortByDesc('_priority')
            ->unique('room_id')
            ->keyBy('room_id');
    }

    private function isCurrentItem(array $item, string $nowTime): bool
    {
        $start = $item['start_time'] ?? null;
        $end = $item['end_time'] ?? null;

        if (! $start || ! $end) {
            return false;
        }

        if ($nowTime < $start || $nowTime >= $end) {
            return false;
        }

        return ! in_array($item['status'] ?? null, ['cancelled', 'auto_cancelled', 'completed'], true);
    }

    private function toFloorplanStatus(array $item): string
    {
        $status = $item['status'] ?? 'unknown';
        $source = $item['source'] ?? null;

        if ($source === 'override') {
            return match ($status) {
                'reserved' => 'reserved',
                'maintenance', 'unavailable' => 'maintenance',
                default => 'maintenance',
            };
        }

        return match ($status) {
            'ongoing' => 'occupied',
            'scheduled', 'pending', 'reserved' => 'reserved',
            'maintenance', 'unavailable' => 'maintenance',
            default => 'reserved',
        };
    }

    private function priority(array $item): int
    {
        $status = $item['status'] ?? null;
        $source = $item['source'] ?? null;

        if ($source === 'override' && in_array($status, ['maintenance', 'unavailable'], true)) {
            return 50;
        }

        if ($status === 'ongoing') {
            return 40;
        }

        if ($source === 'override' && $status === 'reserved') {
            return 35;
        }

        if (in_array($status, ['scheduled', 'pending', 'reserved'], true)) {
            return 30;
        }

        return 10;
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
