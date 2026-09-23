<?php

use App\Http\Controllers\PointTransactionController;
use Illuminate\Support\Facades\Route;

/*
 ┌──────────────────────────────────────────────────────────────────────┐
 │  Point Routes                                                        │
 ├──────────────────────────────────────────────────────────────────────┤
 │  [Pasien]                                                            │
 │    GET  /api/points/balance          – Saldo & info poin pasien      │
 │    GET  /api/points/history          – Riwayat mutasi poin           │
 │    GET  /api/points/preview-booking  – Preview diskon poin booking   │
 │                                                                      │
 │  [Admin / Super Admin]                                               │
 │    GET  /api/admin/point-settings    – Baca konfigurasi poin         │
 │    PUT  /api/admin/point-settings    – Update konfigurasi poin       │
 │    POST /api/admin/points/expire     – Trigger expire manual         │
 │    GET  /api/admin/points/history    – Riwayat semua pasien          │
 └──────────────────────────────────────────────────────────────────────┘
*/

// ── Pasien ─────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:pasien'])->prefix('points')->group(function () {
    // Saldo poin pasien
    Route::get('/balance', [PointTransactionController::class, 'balance']);

    // Riwayat mutasi poin
    Route::get('/history', [PointTransactionController::class, 'history']);

    // Preview diskon poin sebelum submit booking
    // Pasien kirim total_tagihan + points_to_use, sistem kembalikan kalkulasi diskon
    Route::get('/preview-booking', [PointTransactionController::class, 'previewBooking']);
});

// ── Admin / Super Admin ────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:admin,superadmin'])->prefix('admin')->group(function () {
    Route::get('/point-settings',   [PointTransactionController::class, 'getSettings']);
    Route::put('/point-settings',   [PointTransactionController::class, 'updateSettings']);
    Route::post('/points/expire',   [PointTransactionController::class, 'triggerExpire']);
    Route::get('/points/history',   [PointTransactionController::class, 'adminHistory']);
});
