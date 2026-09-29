<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\KeepAliveController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ---------- Guests only (not logged in) ----------
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

// ---------- Every logged-in, active user ----------
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::post('/keep-alive', KeepAliveController::class)->name('keep-alive');
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/my-password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/my-password', [PasswordController::class, 'update'])->name('password.update');

    // Rooms & Inquiries: everyone views availability and logs/updates inquiries.
    Route::resource('rooms', RoomController::class)->only(['index']);
    Route::resource('inquiries', InquiryController::class)->only(['index', 'store', 'edit', 'update']);

    // ---------- Owner only ----------
    Route::middleware('role:Owner')->group(function () {
        Route::resource('rooms', RoomController::class)->only(['create', 'store', 'edit', 'update']);
        Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::patch('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
    });
});
