<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Module 1: open while there is no login yet. Module 2 puts this behind the 'auth' middleware.
Route::get('/', DashboardController::class)->name('dashboard');
