<?php

use App\Http\Controllers\Api\FloorStatusController;
use App\Http\Controllers\Kiosk\DashboardController;
use Illuminate\Support\Facades\Route;


/*
==================================================================================
PUBLIC (KIOSK) ROUTES
==================================================================================
*/

Route::prefix('kiosk')->name('kiosk.')->group(function () {
    Route::redirect('/', '/kiosk/dashboard');
    
    # Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('index');

    # DASHBOARD - API
    Route::get('/dashboard/floor-status', [FloorStatusController::class, 'index'])
    ->name('dashboard.floor-status');
});
