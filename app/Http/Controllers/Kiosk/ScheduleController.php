<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Services\DailyOperationReadService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly DailyOperationReadService $dailyOperationReadService,
    ) {}

    /**
     * Public read-only schedule board.
     *
     * This intentionally uses the same read service as Admin Daily Operations,
     * but exposes no mutation actions to the kiosk/public page.
     */
    public function index(Request $request): Response
    {
        $selectedDateInput = $request->query(
            'selected_date',
            $request->query('date', now()->toDateString())
        );

        return Inertia::render(
            'Kiosk/schedule/index',
            $this->dailyOperationReadService->payloadForDate($selectedDateInput)
        );
    }
}
