<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    /**
     * Return active public announcements for the kiosk/public announcement page.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->integer('limit', 50);
        $limit = max(1, min($limit, 50));

        $announcements = Notice::query()
            ->with('room:id,code,name')
            ->published()
            ->active()
            ->visible()
            ->orderByDesc('is_pinned')
            ->orderByRaw("CASE WHEN source_type = 'schedule_exception' THEN 1 WHEN source_type = 'room_override' THEN 2 ELSE 0 END")
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Notice $notice) => $notice->toKioskAnnouncement())
            ->values();

        return response()->json([
            'data' => $announcements,
            'meta' => [
                'count' => $announcements->count(),
                'last_updated_at' => now()->toIso8601String(),
                'poll_interval_ms' => (int) config('room-system.kiosk_poll_interval', 15000),
            ],
        ]);
    }
}
