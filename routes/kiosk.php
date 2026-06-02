<?php

use App\Http\Controllers\Api\FloorStatusController;
use App\Http\Controllers\Api;
use App\Http\Controllers\Kiosk;
use App\Http\Controllers\Kiosk\DashboardController;
use App\Http\Controllers\Kiosk\ScheduleController;
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

    # Schedules
    Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedule');

    # Annoucements
    Route::get('/announcements', [Kiosk\AnnouncementController::class, 'index'])->name('announcements');

    # Annoucements - API (temporary)
    Route::get('/api/announcements',         [Api\AnnouncementController::class, 'index'])->name('api.announcements');
    Route::get('/api/announcements/count', [Api\AnnouncementController::class, 'count'])->name('api.announcements.count');
});
