<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContentManagementController;

// Public routes (No authentication needed)
Route::prefix('resource/content')->group(function () {
    Route::get('/home', [ContentManagementController::class, 'getHome']);
    Route::get('/about', [ContentManagementController::class, 'getAbout']);
});

// Admin routes (Requires Authentication and Admin Role)
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('resource/content')->group(function () {
    Route::post('/home', [ContentManagementController::class, 'updateHome']);
    Route::post('/about', [ContentManagementController::class, 'updateAbout']);
});
