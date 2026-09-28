<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\WebSocketController;
use App\Http\Controllers\UlasanController;

Route::post('/booking/charge', [BookingController::class, 'charge']);
Route::get('/booking/nakes-terdekat', [BookingController::class, 'getNearestNakesList']);

Route::middleware(['auth:sanctum'])->group(function () {
    // WebSocket & Real-time Chat
    Route::get('/websocket/config', [WebSocketController::class, 'getConfig']);
    Route::get('/booking/{id}/chat', [WebSocketController::class, 'adminRoomDetail']);
    Route::post('/booking/{id}/chat', [WebSocketController::class, 'sendChatMessage']);
    Route::delete('/booking/{id}/chat-room', [WebSocketController::class, 'closeChatRoom']);

    // Mini Ulasan Pasca Pelayanan Endpoints
    Route::get('/booking/pending-review', [UlasanController::class, 'getPendingReviews']);
    Route::post('/booking/{id}/mini-ulasan', [UlasanController::class, 'storeMiniUlasan']);

    // Booking Endpoints
    Route::post('/booking', [BookingController::class, 'store']);
    Route::get('/booking/terkini', [BookingController::class, 'pasienActiveTracking']);
    Route::get('/booking', [BookingController::class, 'index']);
    Route::post('/booking/{id}/cancel', [BookingController::class, 'batalkanBooking']);
    Route::get('/booking/transaksi/{id_transaksi}', [BookingController::class, 'checkStatus']);
    Route::get('/booking/{id}/laporan', [BookingController::class, 'laporan']);
    Route::get('/booking/{id}/payment-details', [BookingController::class, 'getPaymentDetails']);
    Route::get('/booking/{booking_code}/biaya-tambahan', [BookingController::class, 'additionalBhpPaymentStatus']);
    Route::post('/booking/charge-biaya-tambahan', [BookingController::class, 'chargeAdditionalBhp']);
    Route::get('/booking/{id}', [BookingController::class, 'show']);
    Route::patch('/booking/{id}/status', [BookingController::class, 'updateStatus']);

    // Transaksi Endpoints (Pasien View)
    Route::get('/transaksi', [BookingController::class, 'index']);
});
