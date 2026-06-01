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
            ->forKiosk()
            ->visible()
            ->orderByDesc('is_pinned')
            ->orderBy('design_variant')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Notice $notice) => $notice->toKioskAnnouncement())
            ->values();

        return Inertia::render('Kiosk/announcement/index', [
            'announcements' => $announcements,
        ]);
    }
}
