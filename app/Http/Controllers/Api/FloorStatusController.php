<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\DailyOperationReadService;
use App\Services\Support\ValueNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FloorStatusController extends Controller
{
    public function __construct(
        private readonly DailyOperationReadService $dailyOperationReadService,
        private readonly ValueNormalizer $normalizer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());
        $floor = $request->integer('floor', 3);
        $nowTime = now()->format('H:i');

        $operationPayload = $this->dailyOperationReadService->payloadForDate($date);
        $claimGraceMinutes = max(1, (int) ($operationPayload['claim_grace_minutes'] ?? 60));

        $dailySchedules = collect($operationPayload['daily_schedules'] ?? [])
            ->map(fn (array $item) => $this->applyClientSideUnclaimedStatus($item, $claimGraceMinutes));

        $activeRooms = Room::query()
            ->where('is_active', true)
            ->where('floor', $floor)
            ->orderBy('display_order')
            ->orderBy('code')
            ->get()
            ->keyBy('id');

        $currentItemsByRoom = $this->currentItemsByRoom($dailySchedules, $nowTime);
        $itemsByRoom = $this->itemsByRoom($dailySchedules, $activeRooms->keys());

        $data = $activeRooms
            ->map(function (Room $room) use ($currentItemsByRoom, $itemsByRoom, $nowTime) {
                $item = $currentItemsByRoom->get($room->id);
                $roomItems = $itemsByRoom->get($room->id, collect());
                $status = $item ? $this->toFloorplanStatus($item) : 'available';

                return [
                    // Important: this must match floorplanRooms[].id.
                    'room_id'            => $room->code,
                    'code'               => $room->code,
                    'label'              => $room->name,
                    'room_type'          => $this->normalizer->enumValue($room->room_type),
                    'floor'              => $room->floor,
                    'capacity'           => $room->capacity,
                    'status'             => $status,

                    // Current Daily Operations item, used by the floor map labels.
                    'daily_item_id'      => $item['id'] ?? null,
                    'raw_status'         => $item['status'] ?? null,
                    'source'             => $item['source'] ?? null,
                    'event_type'         => $item['event_type'] ?? null,
                    'subject_code'       => $item['subject_code'] ?? null,
                    'subject'            => $item['subject_title'] ?? null,
                    'section'            => $item['section'] ?? null,
                    'year_level'         => $this->yearLevel($item['section'] ?? null),
                    'teacher'            => $item['instructor_name'] ?? null,
                    'starts_at'          => $item['start_time'] ?? null,
                    'ends_at'            => $item['end_time'] ?? null,
                    'reason'             => $item['reason'] ?? null,

                    // Explicit aliases for the map overlay.
                    'current_section'    => $item['section'] ?? null,
                    'current_year_level' => $this->yearLevel($item['section'] ?? null),
                    'current_subject'    => $item['subject_title'] ?? null,
                    'current_time_range' => $item ? $this->timeRangeLabel($item) : null,

                    // Full room sheet data. This is still the same Daily Operations result,
                    // only transformed for the kiosk floor map component.
                    'items'              => $roomItems
                        ->map(fn (array $roomItem) => $this->toRoomScheduleItem($roomItem, $nowTime))
                        ->values(),
                ];
            })
            ->values();

        return response()->json($data);
    }

    private function itemsByRoom(Collection $dailySchedules, Collection $roomIds): Collection
    {
        return $dailySchedules
            ->filter(fn (array $item) => $roomIds->contains((int) ($item['room_id'] ?? 0)))
            ->sortBy([
                ['start_time', 'asc'],
                ['end_time', 'asc'],
                ['source', 'asc'],
            ])
            ->groupBy('room_id');
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

        return ! in_array($item['status'] ?? null, ['cancelled', 'auto_cancelled', 'completed', 'unclaimed'], true);
    }

    private function applyClientSideUnclaimedStatus(array $item, int $claimGraceMinutes): array
    {
        if (($item['source'] ?? null) !== 'schedule' || ($item['event_type'] ?? null) !== 'regular') {
            return $item;
        }

        if (! in_array($item['status'] ?? null, ['scheduled', 'pending'], true)) {
            return $item;
        }

        if (! empty($item['actual_start']) || ! empty($item['actual_end']) || ! empty($item['claimed_at'])) {
            return $item;
        }

        $eventDate = $item['event_date'] ?? null;
        $startTime = $item['start_time'] ?? null;
        $endTime = $item['end_time'] ?? null;

        if (! $eventDate || ! $startTime || ! $endTime) {
            return $item;
        }

        try {
            $start = Carbon::parse("{$eventDate} {$startTime}");
            $end = Carbon::parse("{$eventDate} {$endTime}");
        } catch (\Throwable) {
            return $item;
        }

        $deadline = $start->copy()->addMinutes($claimGraceMinutes);

        if ($deadline->greaterThanOrEqualTo($end)) {
            return $item;
        }

        $item['claim_deadline_at'] = $item['claim_deadline_at'] ?? $deadline->toIso8601String();
        $item['claim_deadline_time'] = $item['claim_deadline_time'] ?? $deadline->format('H:i');

        if (now()->greaterThanOrEqualTo($deadline)) {
            $item['status'] = 'unclaimed';
        }

        return $item;
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
            default => 'available',
        };
    }

    private function toRoomScheduleItem(array $item, string $nowTime): array
    {
        $section = $item['section'] ?? null;
        $status = $item['status'] ?? null;

        return [
            'id'                 => $item['id'] ?? null,
            'schedule_id'        => $item['schedule_id'] ?? null,
            'exception_id'       => $item['exception_id'] ?? null,
            'override_id'        => $item['override_id'] ?? null,
            'room_id'            => $item['room_id'] ?? null,
            'original_room_id'   => $item['original_room_id'] ?? null,
            'original_room_code' => $item['original_room_code'] ?? null,
            'event_date'         => $item['event_date'] ?? null,
            'source'             => $item['source'] ?? null,
            'event_type'         => $item['event_type'] ?? null,
            'status'             => $status,
            'floorplan_status'   => $this->toFloorplanStatus($item),
            'subject_code'       => $item['subject_code'] ?? null,
            'subject_title'      => $item['subject_title'] ?? null,
            'section'            => $section,
            'year_level'         => $this->yearLevel($section),
            'instructor_name'    => $item['instructor_name'] ?? null,
            'start_time'         => $item['start_time'] ?? null,
            'end_time'           => $item['end_time'] ?? null,
            'time_range'         => $this->timeRangeLabel($item),
            'reason'             => $item['reason'] ?? null,
            'claimed_at'         => $item['claimed_at'] ?? null,
            'claim_deadline_at'  => $item['claim_deadline_at'] ?? null,
            'claim_deadline_time'=> $item['claim_deadline_time'] ?? null,
            'is_current'         => $this->isCurrentItem($item, $nowTime),
        ];
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

    private function yearLevel(?string $section): ?string
    {
        if (! $section) {
            return null;
        }

        if (preg_match('/BSCPE\s*([1-4])/i', $section, $matches)) {
            return $matches[1];
        }

        if (preg_match('/(?:^|\s)([1-4])(?:[-\s]|$)/', $section, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function timeRangeLabel(array $item): ?string
    {
        $start = $item['start_time'] ?? null;
        $end = $item['end_time'] ?? null;

        if (! $start || ! $end) {
            return null;
        }

        return "{$this->formatTime($start)}–{$this->formatTime($end)}";
    }

    private function formatTime(string $time): string
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '00');
        $hour = (int) $hour;

        return sprintf('%d:%s%s', $hour % 12 ?: 12, str_pad((string) $minute, 2, '0', STR_PAD_LEFT), $hour < 12 ? 'AM' : 'PM');
    }
}
