<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TenagaMedisController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\NakesBookingController;
use App\Http\Controllers\WebSocketController;

Route::middleware(['auth:sanctum', 'role:nakes,tenaga medis'])->group(function () {
    Route::get('/tenaga-medis', [TenagaMedisController::class, 'show']);
    Route::put('/tenaga-medis', [TenagaMedisController::class, 'update']);
    Route::delete('/tenaga-medis', [TenagaMedisController::class, 'destroy']);
    Route::get('/nakes/data-operasional', [NakesOperasionalController::class, 'index']);
    Route::post('/nakes/data-operasional', [NakesOperasionalController::class, 'store']);
    Route::post('/nakes/update-lokasi', [WebSocketController::class, 'updateNakesLocation']);

    // Order management for Nakes (NakesBookingController)
    Route::get('/nakes/booking', [NakesBookingController::class, 'index']);
    Route::get('/nakes/orders', [NakesBookingController::class, 'ordersQueue']);
    Route::get('/nakes/order/{id}', [NakesBookingController::class, 'show']);
    Route::post('/nakes/booking/{id}/terima', [NakesBookingController::class, 'acceptBooking']);
    Route::post('/nakes/booking/{id}/tolak', [NakesBookingController::class, 'rejectBooking']);
    Route::post('/nakes/booking/{id}/tindakan', [NakesBookingController::class, 'startTindakan']);
    Route::get('/nakes/booking/{id}/bhp', [NakesBookingController::class, 'getBhpList']);
    Route::post('/nakes/booking/{id}/bhp', [NakesBookingController::class, 'updateBhp']);
    Route::post('/nakes/booking/{id}/selesai', [NakesBookingController::class, 'completeBooking']);
});

// Route untuk nakes (termasuk yang butuh endpoint nakes order)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/nakes/complete-data', [TenagaMedisController::class, 'completeData']);
    Route::get('/nakes/pakta-integritas/download', [TenagaMedisController::class, 'downloadPaktaIntegritas']);

    // Alternate direct endpoints for accepting order & updating status
    // Route::get('/nakes/orders', [BookingController::class, 'nakesOrderQueue']);
    // Route::get('/nakes/order/{id}', [BookingController::class, 'nakesOrderDetail']);
    // Route::post('/nakes/order/{id}/accept', [BookingController::class, 'nakesAcceptBooking']);
    // Route::post('/nakes/order/{id}/reject', [BookingController::class, 'nakesRejectBooking']);
    // Route::post('/nakes/order/{id}/status', [BookingController::class, 'nakesUpdateStatus']);
});

