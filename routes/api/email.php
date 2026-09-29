<?php

use App\Http\Controllers\Admin\EmailNotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Email Notification Routes
|--------------------------------------------------------------------------
|
| Admin API untuk pengelolaan pengiriman email via Resend:
| - Kirim email tunggal / custom
| - Broadcast email promo / pengumuman
| - Kirim ulang invoice booking
| - Log riwayat email
|
*/

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/emails')->group(function () {
    Route::post('/send', [EmailNotificationController::class, 'send']);
    Route::post('/broadcast', [EmailNotificationController::class, 'broadcast']);
    Route::post('/send-invoice/{id_booking}', [EmailNotificationController::class, 'sendInvoice']);
    Route::get('/logs', [EmailNotificationController::class, 'logs']);
});
