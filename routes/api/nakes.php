<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TenagaMedisController;



Route::middleware(['auth:sanctum', 'role:nakes'])->group(function () {
    Route::get('/tenaga-medis', [TenagaMedisController::class, 'show']);
    Route::put('/tenaga-medis', [TenagaMedisController::class, 'update']);
    Route::delete('/tenaga-medis', [TenagaMedisController::class, 'destroy']);
});

// Route untuk nakes yang sudah approved tapi belum complete data (tidak butuh role:nakes)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/nakes/complete-data', [TenagaMedisController::class, 'completeData']);
    Route::get('/nakes/pakta-integritas/download', [TenagaMedisController::class, 'downloadPaktaIntegritas']);
});
