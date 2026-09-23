<?php

use App\Http\Controllers\PointTransactionController;
use Illuminate\Support\Facades\Route;

/*

 [Pasien]
   GET  /api/points/balance   – Saldo & info poin pasien yang login
   GET  /api/points/history   – Riwayat mutasi poin 

 [Admin / Super Admin]
  GET  /api/admin/point-settings        – Baca konfigurasi poin
  PUT  /api/admin/point-settings        – Update konfigurasi poin
   POST /api/admin/points/expire         – Trigger expire manual
   GET  /api/admin/points/history        – Riwayat semua pasien

*/

// Pasien
Route::middleware(['auth:sanctum', 'role:pasien'])->prefix('points')->group(function () {
    Route::get('/balance', [PointTransactionController::class, 'balance']);
    Route::get('/history', [PointTransactionController::class, 'history']);
});

//  Admin
Route::middleware(['auth:sanctum', 'role:admin,superadmin'])->prefix('admin')->group(function () {
    Route::get('/point-settings',   [PointTransactionController::class, 'getSettings']);
    Route::put('/point-settings',   [PointTransactionController::class, 'updateSettings']);
    Route::post('/points/expire',   [PointTransactionController::class, 'triggerExpire']);
    Route::get('/points/history',   [PointTransactionController::class, 'adminHistory']);
});
