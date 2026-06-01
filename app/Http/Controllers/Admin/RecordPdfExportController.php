<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RecordPdfExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordPdfExportController extends Controller
{
    public function __construct(
        private readonly RecordPdfExportService $recordPdfExportService,
    ) {}

    public function roomUsage(Request $request): Response
    {
        $pdf = Pdf::loadView('pdf.records.room-usage', [
            'title'       => 'Room Usage Logs Report',
            'logs'        => $this->recordPdfExportService->roomUsageRecords($request),
            'filters'     => $this->recordPdfExportService->roomUsageFilterSummary($request),
            'generatedAt' => now()->format('M d, Y h:i A'),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('room-usage-logs-'.now()->format('Ymd-His').'.pdf');
    }

    public function activity(Request $request): Response
    {
        $pdf = Pdf::loadView('pdf.records.activity', [
            'title'       => 'Admin Activity Logs Report',
            'logs'        => $this->recordPdfExportService->activityRecords($request),
            'filters'     => $this->recordPdfExportService->activityFilterSummary($request),
            'generatedAt' => now()->format('M d, Y h:i A'),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('admin-activity-logs-'.now()->format('Ymd-His').'.pdf');
    }
}
