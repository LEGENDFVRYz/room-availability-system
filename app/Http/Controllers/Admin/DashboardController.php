<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Schedule;
use App\Services\AcademicTermService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AcademicTermService $academicTermService,
    ) {}

    public function index(): Response
    {
        $now  = now();
        $day  = (int) $now->format('N'); // 1=Mon … 7=Sun (matches DayOfWeek enum)
        $time = $now->format('H:i:s');

        $term = $this->academicTermService->getCurrent();

        // Room IDs that have a class running right now
        $occupiedIds = $term
            ? Schedule::where('academic_term_id', $term->id)
                ->where('is_active', true)
                ->where('day_of_week', $day)
                ->where('start_time', '<=', $time)
                ->where('end_time', '>', $time)
                ->distinct()
                ->pluck('room_id')
                ->toArray()
            : [];

        $activeTotal = Room::where('is_active', true)->count();

        return Inertia::render('Admin/dashboard', [
            'roomStats' => [
                'available'   => $activeTotal - count($occupiedIds),
                'occupied'    => count($occupiedIds),
                'reserved'    => 0, // room overrides not yet implemented
                'maintenance' => Room::where('is_active', false)->count(),
            ],
        ]);
    }
}
