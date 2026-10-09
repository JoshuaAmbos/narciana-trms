<?php

use App\Http\Controllers\ApplianceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\ConcernController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\KeepAliveController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\MeterReadingController;
use App\Http\Controllers\OccupantController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisitorController;
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

    // Rooms (availability) and Inquiries (log/update) are separate modules; everyone uses both (decision 19).
    Route::resource('rooms', RoomController::class)->only(['index']);
    Route::resource('inquiries', InquiryController::class)->only(['index', 'store', 'edit', 'update']);
    Route::patch('/inquiries/{inquiry}/status', [InquiryController::class, 'updateStatus'])->name('inquiries.status');

    // Tenants & MOA: every role opens the list (the Caretaker gets a limited version) and
    // manages appliances and visitors. MOA, rent, deposits and occupants are Owner/Manager only (below).
    Route::resource('tenants', TenantController::class)->only(['index']);
    Route::get('/leases/{lease}/household', [HouseholdController::class, 'show'])->name('leases.household');
    Route::resource('leases.appliances', ApplianceController::class)->only(['store']);
    Route::patch('/appliances/{appliance}/remove', [ApplianceController::class, 'remove'])->name('appliances.remove');
    Route::resource('leases.visitors', VisitorController::class)->only(['store']);
    Route::patch('/visitors/{visitor}/depart', [VisitorController::class, 'depart'])->name('visitors.depart');

    // Concerns: every role logs and updates (cost / charged to tenant: Owner/Manager, enforced in ConcernService).
    Route::resource('concerns', ConcernController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::get('/concerns/{concern}/photo/{kind}', [ConcernController::class, 'photo'])
        ->whereIn('kind', ['reported', 'resolved'])->name('concerns.photo'); // private photos (decision 18)

    // ---------- Owner and Manager ----------
    Route::middleware('role:Owner,Manager')->group(function () {
        Route::resource('tenants', TenantController::class)->only(['create', 'store', 'show', 'edit', 'update']);
        Route::patch('/tenants/{tenant}/archive', [TenantController::class, 'archive'])->name('tenants.archive');
        Route::patch('/tenants/{tenant}/restore', [TenantController::class, 'restore'])->withTrashed()->name('tenants.restore');
        Route::resource('leases', LeaseController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        Route::patch('/leases/{lease}/end', [LeaseController::class, 'endLease'])->name('leases.end');
        Route::patch('/leases/{lease}/cancel', [LeaseController::class, 'cancel'])->name('leases.cancel');
        Route::resource('leases.occupants', OccupantController::class)->only(['store']);
        Route::patch('/occupants/{occupant}/remove', [OccupantController::class, 'remove'])->name('occupants.remove');

        // Billing
        Route::resource('bills', BillController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
        Route::resource('meter-readings', MeterReadingController::class)->only(['index']);

        // Payments (voiding is Owner-only, checked by PaymentPolicy)
        Route::resource('payments', PaymentController::class)->only(['index', 'create', 'store', 'show', 'destroy']);

        // Reports (printable; filters are GET parameters)
        Route::controller(ReportController::class)->prefix('reports')->name('reports.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/collections', 'collections')->name('collections');
            Route::get('/due', 'due')->name('due');
            Route::get('/statement', 'statement')->name('statement');
            Route::get('/concerns', 'concerns')->name('concerns');
            Route::get('/inquiries', 'inquiries')->name('inquiries');
        });
    });

    // ---------- Owner only ----------
    Route::middleware('role:Owner')->group(function () {
        Route::resource('rooms', RoomController::class)->only(['create', 'store', 'edit', 'update']);
        Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::patch('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');

        // Settings (rates and rules: one row, so no {id} in the URL)
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

        // Backup and Restore ({backup} = file name, checked by BackupService)
        Route::resource('backups', BackupController::class)->only(['index', 'store', 'destroy']);
        Route::get('/backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');
        Route::post('/backups/restore', [BackupController::class, 'restore'])->name('backups.restore');

        // Audit Log: view only (no edit or delete routes, on purpose)
        Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
    });
});
