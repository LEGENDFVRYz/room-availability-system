<?php

namespace App\Services;

use App\Models\Room;
use Illuminate\Database\Eloquent\Collection;

class RoomService
{
    private readonly NoticeService $noticeService;

    public function __construct(?NoticeService $noticeService = null)
    {
        $this->noticeService = $noticeService ?? app(NoticeService::class);
    }

    public function getAll(): Collection
    {
        return Room::orderBy('display_order')
            ->orderBy('code')
            ->get();
    }

    public function create(array $data): Room
    {
        return Room::create($data);
    }

    public function update(Room $room, array $data): Room
    {
        $room->update($data);
        return $room->fresh();
    }

    public function deactivate(Room $room, ?int $userId = null): void
    {
        $this->noticeService->deactivateRoomScopedNotices($room->id, $userId);

        $room->update(['is_active' => false]);

        $this->noticeService->announceRoomDeactivated($room->fresh(), $userId);
    }
}
