<?php

use App\Http\Controllers\Api\FloorStatusController;
use App\Http\Controllers\Kiosk\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


/*
==================================================================================
PUBLIC (KIOSK) ROUTES
==================================================================================
*/

Route::prefix('kiosk')->name('kiosk.')->group(function () {
    Route::redirect('/', '/kiosk/dashboard');
    
    # Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('index');

    # DASHBOARD - API (temporary)
    Route::get('/dashboard/floor-status', [FloorStatusController::class, 'index'])->name('dashboard.floor-status');

    # Annoucements
    Route::get('/announcements', function () {
        return Inertia::render('Kiosk/announcement/index');
    })->name('announcements');
});
