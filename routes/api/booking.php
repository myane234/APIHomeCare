<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookingController;

Route::post('/booking/charge', [BookingController::class, 'charge']);
Route::get('/booking/nakes-terdekat', [BookingController::class, 'getNearestNakesList']);

Route::middleware(['auth:sanctum'])->group(function () {
    // Booking Endpoints
    Route::post('/booking', [BookingController::class, 'store']);
    Route::get('/booking', [BookingController::class, 'index']);
    Route::post('/booking/{id}/cancel', [BookingController::class, 'batalkanBooking']);
    Route::get('/booking/transaksi/{id_transaksi}', [BookingController::class, 'checkStatus']);
    Route::get('/booking/{id}/laporan', [BookingController::class, 'laporan']);
    Route::get('/booking/{id}/payment-details', [BookingController::class, 'getPaymentDetails']);
    Route::get('/booking/{id}', [BookingController::class, 'show']);
    Route::patch('/booking/{id}/status', [BookingController::class, 'updateStatus']);

    // Transaksi Endpoints (Pasien View)
    Route::get('/transaksi', [BookingController::class, 'index']);
});
