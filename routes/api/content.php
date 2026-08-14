<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContentManagementController;
use App\Http\Controllers\KategoriArtikelController;

// Public routes (No authentication needed)
Route::prefix('resource/content')->group(function () {
    Route::get('/home', [ContentManagementController::class, 'getHome']);
    Route::get('/about', [ContentManagementController::class, 'getAbout']);
    
    // Kategori Artikel (Public Read)
    Route::get('/artikel/kategori', [KategoriArtikelController::class, 'index']);
});

// Admin routes (Requires Authentication and Admin Role)
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('resource/content')->group(function () {
    Route::post('/home', [ContentManagementController::class, 'updateHome']);
    Route::post('/about', [ContentManagementController::class, 'updateAbout']);

    // Kategori Artikel CRUD (Admin only)
    Route::post('/artikel/kategori', [KategoriArtikelController::class, 'store']);
    Route::get('/artikel/kategori/{id}', [KategoriArtikelController::class, 'show']);
    Route::put('/artikel/kategori/{id}', [KategoriArtikelController::class, 'update']);
    Route::delete('/artikel/kategori/{id}', [KategoriArtikelController::class, 'destroy']);
});
