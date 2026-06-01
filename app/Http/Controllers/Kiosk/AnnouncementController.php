<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(): Response
    {
        $announcements = Notice::query()
            ->with('room:id,code,name')
            ->published()
            ->active()
            ->visible()
            ->orderByDesc('is_pinned')
            ->orderByRaw("CASE WHEN source_type = 'schedule_exception' THEN 1 WHEN source_type = 'room_override' THEN 2 ELSE 0 END")
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (Notice $notice) => $notice->toKioskAnnouncement())
            ->values();

        return Inertia::render('Kiosk/announcement/index', [
            // Initial data prevents the page from looking empty on first load.
            // The Vue page refreshes this through /api/kiosk/announcements after mount.
            'announcements' => $announcements,
            'pollIntervalMs' => (int) config('room-system.kiosk_poll_interval', 15000),
        ]);
    }
}
