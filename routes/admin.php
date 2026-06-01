<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ManageController;
use App\Http\Controllers\Admin\OperationController;
use App\Http\Controllers\Admin\RecordController;
use App\Http\Controllers\Admin\RecordPdfExportController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\ScheduleImportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


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
    Route::post('/schedules/import',              [ScheduleImportController::class, '__invoke'])->name('schedules.import');
    Route::patch('/schedules/{schedule}',         [ScheduleController::class, 'update'])->name('schedules.update');
    Route::delete('/schedules/{schedule}',        [ScheduleController::class, 'destroy'])->name('schedules.destroy');
    

    # Operations 
    Route::redirect('/operations', '/admin/operations/daily')->name('operations');

    # Operations - Daily Schedule
    Route::get('/operations/daily',                                   [OperationController::class, 'daily'])->name('operations.daily');
    Route::post('/operations/daily/request-class',                    [OperationController::class, 'requestClass'])->name('operations.daily.request-class');
    Route::post('/operations/daily/cancel',                           [OperationController::class, 'cancelClass'])->name('operations.daily.cancel');
    Route::post('/operations/daily/change-room',                      [OperationController::class, 'changeRoom'])->name('operations.daily.change-room');
    Route::patch('/operations/daily/mark-started',                    [OperationController::class, 'markStarted'])->name('operations.daily.mark-started');
    Route::patch('/operations/daily/mark-completed',                  [OperationController::class, 'markCompleted'])->name('operations.daily.mark-completed');
    Route::patch('/operations/daily/revert-started',                  [OperationController::class, 'revertStarted'])->name('operations.daily.revert-started');
    Route::patch('/operations/daily/revert-completed',                [OperationController::class, 'revertCompleted'])->name('operations.daily.revert-completed');
    Route::patch('/operations/daily/revert-cancellation',             [OperationController::class, 'revertCancellation'])->name('operations.daily.revert-cancellation');
    Route::delete('/operations/daily/exceptions/{scheduleException}', [OperationController::class, 'destroyException'])->name('operations.daily.exceptions.destroy');

    # Operations - Room Status
    Route::get('/operations/room-status',                        [OperationController::class, 'rooms'])->name('operations.room-status');
    Route::post('/operations/room-status',                       [OperationController::class, 'storeRoomOverride'])->name('operations.room-status.store');
    Route::patch('/operations/room-status/{roomOverride}',       [OperationController::class, 'updateRoomOverride'])->name('operations.room-status.update');
    Route::patch('/operations/room-status/{roomOverride}/clear', [OperationController::class, 'clearRoomOverride'])->name('operations.room-status.clear');
    
    # Records 
    Route::redirect('/records', '/admin/records/usage')->name('records');

    Route::get('/records/usage',        [RecordController::class, 'roomUsage'])->name('records.usage');
    Route::get('/records/activity',     [RecordController::class, 'activity'])->name('records.activity');

    # Records (Api) - temporary
    Route::get('/records/usage/export/pdf',     [RecordPdfExportController::class, 'roomUsage'])->name('records.usage.export.pdf');
    Route::get('/records/activity/export/pdf',  [RecordPdfExportController::class, 'activity'])->name('records.activity.export.pdf');
    
});