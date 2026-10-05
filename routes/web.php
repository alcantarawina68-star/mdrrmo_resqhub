<?php

use App\Enums\UserRole;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CallerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EvidenceImageController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordConfirmController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SessionManagementController;
use App\Http\Controllers\SiteSettingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'map'])->name('home');
Route::get('/advisories', [PublicController::class, 'advisories'])->name('advisories');
Route::get('/incidents/{incident}', [PublicController::class, 'show'])->name('incidents.show');

// Evidence bytes are read from the database rather than public/storage, so the
// route is public like the file URLs it replaces: the public incident page shows
// the same thumbnails.
Route::get('/evidence/{evidence}/image', EvidenceImageController::class)->name('evidence.image');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/confirm-password', [PasswordConfirmController::class, 'show'])->name('password.confirm');
    Route::post('/confirm-password', [PasswordConfirmController::class, 'store'])->name('password.confirm.submit');
    Route::get('/confirm-password/resume', [PasswordConfirmController::class, 'resume'])->name('password.confirm.resume');
});

Route::middleware(['auth', 'active', 'single-session'])->group(function () {
    Route::get('/report', [IncidentController::class, 'create'])->name('report.create');
    Route::post('/report', [IncidentController::class, 'store'])->name('report.store');
    Route::get('/my-reports', [IncidentController::class, 'myReports'])->name('my-reports');
    Route::get('/my-reports/export', [ReportController::class, 'myReports'])->name('my-reports.export');
    Route::get('/my-reports/export/pdf', [ReportController::class, 'myReportsPdf'])->name('my-reports.export.pdf');
});

/*
 * In-app incident alerts. Operations roles only, because they are the audience
 * IncidentService notifies, so the badge can never sit at zero for a role that
 * will never receive one. The gate reads UserRole::operationsRoles() to match
 * the Blade bell gate and the service recipients.
 */
Route::middleware(['auth', 'active', 'single-session', 'role:'.implode(',', UserRole::operationsRoles())])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

Route::middleware(['auth', 'active', 'single-session', 'role:superadmin,admin,encoder,responder'])->prefix('dashboard')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/incidents', [DashboardController::class, 'incidents'])->name('dashboard.incidents');

    /*
     * Ahead of dashboard.incidents.show on purpose: that route binds {incident}
     * by id, so it would otherwise swallow /incidents/export and answer it with
     * a 404 instead of the file. The gate matches the Reports page, because
     * exporting the whole table is an operations action even though the list
     * itself is readable by responders.
     */
    Route::middleware('role:superadmin,admin,encoder')->group(function () {
        Route::get('/incidents/export', [ReportController::class, 'export'])->name('dashboard.incidents.export');
        Route::get('/incidents/export/pdf', [ReportController::class, 'exportPdf'])->name('dashboard.incidents.export.pdf');
    });

    Route::get('/incidents/{incident}', [DashboardController::class, 'show'])->name('dashboard.incidents.show');

    Route::middleware('role:superadmin,admin,encoder')->group(function () {
        Route::post('/incidents/{incident}/verify', [DashboardController::class, 'verify'])->middleware('reauthenticate')->name('dashboard.incidents.verify');
        Route::post('/incidents/{incident}/notify', [DashboardController::class, 'notifyEmergencyContact'])->middleware('reauthenticate')->name('dashboard.incidents.notify');
        Route::post('/incidents/{incident}/status', [DashboardController::class, 'updateStatus'])->middleware('reauthenticate')->name('dashboard.incidents.status');

        Route::middleware('role:superadmin,admin')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('dashboard.users');
            Route::get('/users/export', [ReportController::class, 'users'])->name('dashboard.users.export');
            Route::get('/users/export/pdf', [ReportController::class, 'usersPdf'])->name('dashboard.users.export.pdf');
            Route::post('/users', [UserController::class, 'store'])->middleware('reauthenticate')->name('dashboard.users.store');
            Route::post('/users/{user}', [UserController::class, 'update'])->middleware('reauthenticate')->name('dashboard.users.update');
            Route::post('/users/{user}/reset-password', [UserController::class, 'sendResetLink'])->middleware('reauthenticate')->name('dashboard.users.reset-password');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('reauthenticate')->name('dashboard.users.destroy');

            Route::get('/settings', [SiteSettingController::class, 'index'])->name('dashboard.settings');
            Route::post('/settings', [SiteSettingController::class, 'update'])->middleware('reauthenticate')->name('dashboard.settings.update');
        });

        Route::middleware('role:superadmin')->group(function () {
            Route::get('/sessions', [SessionManagementController::class, 'index'])->name('dashboard.sessions');
            Route::get('/sessions/export', [ReportController::class, 'sessions'])->middleware('reauthenticate')->name('dashboard.sessions.export');
            Route::get('/sessions/export/pdf', [ReportController::class, 'sessionsPdf'])->middleware('reauthenticate')->name('dashboard.sessions.export.pdf');
            Route::post('/sessions/{session}/terminate', [SessionManagementController::class, 'terminate'])->middleware('reauthenticate')->name('dashboard.sessions.terminate');
            Route::post('/sessions/users/{user}/logout', [SessionManagementController::class, 'logoutUser'])->middleware('reauthenticate')->name('dashboard.sessions.logout-user');

            Route::get('/backup', [BackupController::class, 'index'])->name('dashboard.backup');

            /*
             * A GET on purpose. Reaching this through the password confirmation
             * flow must end in a navigation the browser can act on, and a
             * scripted form replay cannot deliver a download: the browser saves
             * the file and stays on the "Finishing your request" page forever.
             */
            Route::get('/backup/run', [BackupController::class, 'run'])->middleware('reauthenticate')->name('dashboard.backup.run');
        });
    });

    /*
     * Editing recorded incident details is a correction, not an operational
     * decision: responders in the field may fix a description, a landmark, or a
     * misplaced pin. Verify/status/notify above stay operations-only, so this
     * gate is intentionally wider than that one.
     */
    Route::middleware('role:'.implode(',', UserRole::incidentEditorRoles()))->group(function () {
        Route::post('/incidents/{incident}', [DashboardController::class, 'update'])->middleware('reauthenticate')->name('dashboard.incidents.update');
    });

    Route::middleware('role:admin,encoder')->group(function () {
        Route::get('/caller', [CallerController::class, 'create'])->name('dashboard.caller');
        Route::post('/caller', [CallerController::class, 'store'])->middleware('reauthenticate')->name('dashboard.caller.store');

        Route::get('/announcements', [AnnouncementController::class, 'index'])->name('dashboard.announcements');
        Route::get('/announcements/export', [ReportController::class, 'announcements'])->name('dashboard.announcements.export');
        Route::get('/announcements/export/pdf', [ReportController::class, 'announcementsPdf'])->name('dashboard.announcements.export.pdf');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->middleware('reauthenticate')->name('dashboard.announcements.store');
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->middleware('reauthenticate')->name('dashboard.announcements.destroy');

        Route::get('/reports', [ReportController::class, 'index'])->name('dashboard.reports');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('dashboard.reports.export');
        Route::get('/reports/export/pdf', [ReportController::class, 'exportPdf'])->name('dashboard.reports.export.pdf');

        Route::get('/sms/export', [ReportController::class, 'sms'])->middleware('reauthenticate')->name('dashboard.sms.export');
        Route::get('/sms/export/pdf', [ReportController::class, 'smsPdf'])->middleware('reauthenticate')->name('dashboard.sms.export.pdf');
    });
});
