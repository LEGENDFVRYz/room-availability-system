<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ScheduleCsvImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScheduleImportController extends Controller
{
    public function __invoke(Request $request, ScheduleCsvImportService $importer): RedirectResponse
    {
        $validated = $request->validate([
            'year_start' => ['required', 'integer', 'min:2000', 'max:2100'],
            'semester' => ['required', 'integer', 'in:1,2,3'],
            'replace_existing' => ['sometimes', 'boolean'],
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $result = $importer->import(
            file: $request->file('csv_file'),
            yearStart: (int) $validated['year_start'],
            semester: (int) $validated['semester'],
            replaceExisting: $request->boolean('replace_existing'),
            userId: $request->user()?->id,
        );

        $message = $result['imported_count'] . ' schedule row' . ($result['imported_count'] === 1 ? '' : 's') . ' imported.';

        if ($result['skipped_count'] > 0) {
            $message .= ' ' . $result['skipped_count'] . ' row' . ($result['skipped_count'] === 1 ? '' : 's') . ' were not added and are available in the feedback CSV.';
        }

        return back()
            ->with('success', $message)
            ->with('schedule_import', $result);
    }
}
