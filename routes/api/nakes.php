<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TenagaMedisController;
use App\Http\Controllers\BookingController;

Route::middleware(['auth:sanctum', 'role:nakes,tenaga medis'])->group(function () {
    Route::get('/tenaga-medis', [TenagaMedisController::class, 'show']);
    Route::put('/tenaga-medis', [TenagaMedisController::class, 'update']);
    Route::delete('/tenaga-medis', [TenagaMedisController::class, 'destroy']);

    // Order management for Nakes
    Route::get('/nakes/booking', [BookingController::class, 'nakesIndex']);
    Route::post('/nakes/booking/{id}/terima', [BookingController::class, 'nakesAcceptBooking']);
    Route::post('/nakes/booking/{id}/status', [BookingController::class, 'nakesUpdateStatus']);
});

// Route untuk nakes (termasuk yang butuh endpoint nakes order)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/nakes/complete-data', [TenagaMedisController::class, 'completeData']);
    Route::get('/nakes/pakta-integritas/download', [TenagaMedisController::class, 'downloadPaktaIntegritas']);

    // Alternate direct endpoints for accepting order & updating status
    Route::post('/nakes/order/{id}/accept', [BookingController::class, 'nakesAcceptBooking']);
    Route::post('/nakes/order/{id}/status', [BookingController::class, 'nakesUpdateStatus']);
});

