<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentTypeController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SystemSettingsController;
use App\Http\Controllers\Api\StorageController;
use App\Http\Controllers\Api\BackupController;

// Public auth routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/email/verify', [AuthController::class, 'verifyEmail']);
Route::post('/email/resend', [AuthController::class, 'resendOtp']);
Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']);

// Public system settings (for app name & logo)
Route::get('/settings', [SystemSettingsController::class, 'showPublic']);
// Serve storage files via API (same as TheMidTaskApp—works in production without storage:link)
Route::get('/storage/{path}', [StorageController::class, 'serve'])->where('path', '.*');

// Protected routes (Laravel Sanctum, token expires in 8 hours; inactive users get 403)
Route::middleware(['auth:sanctum', 'user.active'])->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/user/password', [AuthController::class, 'changePassword']);

    // Document types (for control number registration)
    Route::get('/document-types', [DocumentTypeController::class, 'index']);

    // Admin-only: user, settings, document type management, backups
    Route::middleware('admin')->group(function () {
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::get('/admin/document-types', [DocumentTypeController::class, 'indexAll']);
        Route::post('/admin/document-types', [DocumentTypeController::class, 'store']);
        Route::put('/admin/document-types/{documentType}', [DocumentTypeController::class, 'update']);

        // System settings
        Route::put('/admin/settings', [SystemSettingsController::class, 'update']);
        Route::post('/admin/settings/logo', [SystemSettingsController::class, 'updateLogo']);
        Route::post('/admin/settings/auth-background', [SystemSettingsController::class, 'updateAuthBackground']);

        // Backups
        Route::get('/admin/backup', [BackupController::class, 'downloadNow']);
        Route::get('/admin/backup/schedule', [BackupController::class, 'showSchedule']);
        Route::put('/admin/backup/schedule', [BackupController::class, 'updateSchedule']);
        Route::get('/admin/backup/list', [BackupController::class, 'listBackups']);
        Route::get('/admin/backup/download/latest', [BackupController::class, 'downloadLatest']);
        Route::get('/admin/backup/download/file/{filename}', [BackupController::class, 'downloadFile']);
    });

    // Control numbers (documents) — specific route before {id}
    Route::get('/documents/by-control-number/{controlNumber}', [DocumentController::class, 'showByControlNumber']);
    Route::post('/documents/{document}/in', [DocumentController::class, 'in']);
    Route::post('/documents/{document}/out', [DocumentController::class, 'out']);
    Route::apiResource('documents', DocumentController::class)->only(['index', 'store', 'show']);

    // Reports (optional ?format=csv for CSV download)
    Route::get('/reports/tracking', [ReportController::class, 'tracking']);
    Route::get('/reports/document-history', [ReportController::class, 'documentHistory']);
    Route::get('/reports/accountability', [ReportController::class, 'accountability']);

    // Admin-only: list users (user management + accountability report dropdown)
    Route::get('/users', [UserController::class, 'index'])->middleware('admin');
});
