<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CallerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'map'])->name('home');
Route::get('/advisories', [PublicController::class, 'advisories'])->name('advisories');
Route::get('/incidents/{incident}', [PublicController::class, 'show'])->name('incidents.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active', 'single-session'])->group(function () {
    Route::get('/report', [IncidentController::class, 'create'])->name('report.create');
    Route::post('/report', [IncidentController::class, 'store'])->name('report.store');
    Route::get('/my-reports', [IncidentController::class, 'myReports'])->name('my-reports');
});

Route::middleware(['auth', 'active', 'single-session', 'role:admin,encoder,barangay_official,responder'])->prefix('dashboard')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/incidents', [DashboardController::class, 'incidents'])->name('dashboard.incidents');
    Route::get('/incidents/{incident}', [DashboardController::class, 'show'])->name('dashboard.incidents.show');

    Route::middleware('role:admin,encoder')->group(function () {
        Route::post('/incidents/{incident}/verify', [DashboardController::class, 'verify'])->name('dashboard.incidents.verify');
        Route::post('/incidents/{incident}/status', [DashboardController::class, 'updateStatus'])->name('dashboard.incidents.status');
        Route::post('/incidents/{incident}', [DashboardController::class, 'update'])->name('dashboard.incidents.update');

        Route::get('/caller', [CallerController::class, 'create'])->name('dashboard.caller');
        Route::post('/caller', [CallerController::class, 'store'])->name('dashboard.caller.store');

        Route::get('/announcements', [AnnouncementController::class, 'index'])->name('dashboard.announcements');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->name('dashboard.announcements.store');
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('dashboard.announcements.destroy');

        Route::get('/reports', [ReportController::class, 'index'])->name('dashboard.reports');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('dashboard.reports.export');

        Route::middleware('role:admin')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('dashboard.users');
            Route::post('/users', [UserController::class, 'store'])->name('dashboard.users.store');
            Route::post('/users/{user}', [UserController::class, 'update'])->name('dashboard.users.update');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('dashboard.users.destroy');
        });
    });
});
