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

    # Manage — Rooms
    Route::get('/manage/rooms',            [ManageController::class, 'rooms'])->name('manage.rooms');
    Route::post('/manage/rooms',           [ManageController::class, 'storeRoom'])->name('manage.rooms.store');
    Route::patch('/manage/rooms/{room}',   [ManageController::class, 'updateRoom'])->name('manage.rooms.update');
    Route::delete('/manage/rooms/{room}',  [ManageController::class, 'deleteRoom'])->name('manage.rooms.delete');

    # Manage — Config
    Route::get('/manage/configs',                        [ManageController::class, 'configs'])->name('manage.configs');
    Route::post('/manage/configs/terms/set-current',     [ManageController::class, 'setCurrentTerm'])->name('manage.configs.set-current');
});