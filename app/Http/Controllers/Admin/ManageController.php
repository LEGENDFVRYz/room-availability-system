<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AcademicTermService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ManageController extends Controller
{
    public function rooms(): Response
    {
        return Inertia::render('Admin/Manage/Rooms');
    }


    // ------------------------------------------------
    //  Academic Configuration
    // ------------------------------------------------
    public function configs(): Response
    {
        $current = (new AcademicTermService())->getCurrent();

        return Inertia::render('Admin/Manage/Configs', [
            'currentTerm' => $current ? [
                'school_year'    => $current->school_year_label,
                'semester_label' => $current->semester->label(),
                'year_start'     => $current->year_start,
                'semester'       => $current->semester->value,
            ] : null,
        ]);
    }
    
    public function setCurrentTerm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year_start' => ['required', 'integer', 'min:2000', 'max:2099'],
            'semester'   => ['required', 'integer', 'in:1,2,3'],
        ]);

        try {
            $term = (new AcademicTermService())->findOrCreateAndSetCurrent(
                (int) $validated['year_start'],
                (int) $validated['semester'],
            );

            return redirect()->route('admin.manage.configs')
                ->with('success', "Set \"{$term->label}\" as the active academic term.");

        } catch (\Throwable $e) {
            Log::error('Failed to set current academic term', ['error' => $e->getMessage()]);

            return redirect()->route('admin.manage.configs')
                ->with('error', 'Failed to update the active term. Please try again.');
        }
    }
}
