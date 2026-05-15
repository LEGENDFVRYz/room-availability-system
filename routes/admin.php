<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ManageController;
use Illuminate\Support\Facades\Route;


/*
==================================================================================
(SUPER) ADMIN ROUTES
==================================================================================
*/

Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified'])->group(function () {
    Route::redirect('/', '/admin/dashboard');
    
    # Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    # Manage
    Route::redirect('/manage', '/admin/manage/rooms')->name('manage');
    Route::get('/manage/rooms',   [ManageController::class, 'rooms'])->name('manage.rooms');
    Route::get('/manage/configs', [ManageController::class, 'configs'])->name('manage.configs');
    Route::post('/manage/configs/terms/set-current', [ManageController::class, 'setCurrentTerm'])->name('manage.configs.set-current');
});