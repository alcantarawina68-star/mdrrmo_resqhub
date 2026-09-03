<?php

use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\IncidentController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->prefix('v1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::get('incidents', [IncidentController::class, 'index']);
    Route::get('incidents/{incident}', [IncidentController::class, 'show']);

    Route::get('announcements', [AnnouncementController::class, 'index']);
    Route::get('announcements/{announcement}', [AnnouncementController::class, 'show']);

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('my/incidents', [IncidentController::class, 'my']);
        Route::post('incidents', [IncidentController::class, 'store']);
    });

    Route::middleware(['auth:sanctum', 'active', 'role:admin,encoder'])->group(function () {
        Route::post('incidents/caller', [IncidentController::class, 'callerBased']);
        Route::post('incidents/{incident}/verify', [IncidentController::class, 'verify']);
        Route::patch('incidents/{incident}/status', [IncidentController::class, 'updateStatus']);
        Route::patch('incidents/{incident}', [IncidentController::class, 'update']);

        Route::post('announcements', [AnnouncementController::class, 'store']);
        Route::patch('announcements/{announcement}', [AnnouncementController::class, 'update']);
        Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy']);

        Route::get('reports/summary', [ReportController::class, 'summary']);
        Route::get('reports/trend', [ReportController::class, 'trend']);
        Route::get('reports/barangays', [ReportController::class, 'barangays']);
    });

    Route::middleware(['auth:sanctum', 'active', 'role:admin'])->group(function () {
        Route::delete('incidents/{incident}', [IncidentController::class, 'destroy']);
        Route::apiResource('users', UserController::class);
        Route::get('reports/export', [ReportController::class, 'export']);
    });
});
