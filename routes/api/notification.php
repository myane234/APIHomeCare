<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\NotificationTemplateController as AdminNotifTemplateController;
use App\Http\Controllers\Api\NotificationController;

/*
|--------------------------------------------------------------------------
| Notification Routes
|--------------------------------------------------------------------------
|
| Admin  : CRUD template + broadcast manual
| User   : Inbox, mark as read, mark all as read
|
*/

// ─── Admin Routes ─────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {

    // CRUD Template
    Route::get('/admin/notification-templates', [AdminNotifTemplateController::class, 'index']);
    Route::post('/admin/notification-templates', [AdminNotifTemplateController::class, 'store']);
    Route::get('/admin/notification-templates/{id}', [AdminNotifTemplateController::class, 'show']);
    Route::put('/admin/notification-templates/{id}', [AdminNotifTemplateController::class, 'update']);
    Route::delete('/admin/notification-templates/{id}', [AdminNotifTemplateController::class, 'destroy']);

    // Broadcast manual
    Route::post('/admin/notification-templates/{id}/broadcast', [AdminNotifTemplateController::class, 'broadcast']);

    // Statistik template
    Route::get('/admin/notification-templates/{id}/stats', [AdminNotifTemplateController::class, 'stats']);

    // Schedule management
    Route::post('/admin/notification-templates/{id}/schedules', [AdminNotifTemplateController::class, 'storeSchedule']);
    Route::delete('/admin/notification-templates/{id}/schedules/{scheduleId}', [AdminNotifTemplateController::class, 'destroySchedule']);
});

// ─── User / Pasien Routes ──────────────────────────────────────────────────────
Route::middleware(['auth:sanctum'])->group(function () {

    // Inbox paginated (GET ?unread_only=1&per_page=20)
    Route::get('/notifications', [NotificationController::class, 'index']);

    // Badge count
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);

    // Mark all as read  ← harus SEBELUM /{id} agar tidak tertangkap sebagai id=read-all
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Mark single as read
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

    // Hapus notifikasi
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
});
