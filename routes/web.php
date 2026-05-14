<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


/*
==================================================================================
WEB STATIC ROUTES
==================================================================================
*/

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

# Fallback Error: Due to laravel startup kit
Route::redirect('/dashboard', '/kiosk/dashboard')->name('dashboard');



require __DIR__.'/admin.php';
require __DIR__.'/kiosk.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
