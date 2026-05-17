<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ManageController;
use App\Http\Controllers\Admin\OperationController;
use App\Http\Controllers\Admin\ScheduleController;
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

    # Schedules
    Route::redirect('/schedules', '/admin/schedules/sections')->name('schedules');
    Route::get('/schedules/sections',             [ScheduleController::class, 'sections'])->name('schedules.sections');
    Route::get('/schedules/rooms',                [ScheduleController::class, 'rooms'])->name('schedules.rooms');
    Route::post('/schedules',                     [ScheduleController::class, 'store'])->name('schedules.store');
    Route::patch('/schedules/{schedule}',         [ScheduleController::class, 'update'])->name('schedules.update');
    Route::delete('/schedules/{schedule}',        [ScheduleController::class, 'destroy'])->name('schedules.destroy');

    # Operations 
    Route::get('/operations/daily',         [OperationController::class, 'daily'])->name('manage.configs');
    Route::get('/operations/room-status',   [OperationController::class, 'rooms'])->name('manage.configs');
    
});