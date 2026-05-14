<?php

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
});
