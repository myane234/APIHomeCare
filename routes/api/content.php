<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContentManagementController;
use App\Http\Controllers\KategoriArtikelController;
use App\Http\Controllers\UlasanController;
use App\Http\Controllers\HubungiKamiController;

// Public routes (No authentication needed)
Route::prefix('resource/content')->group(function () {
    Route::get('/home', [ContentManagementController::class, 'getHome']);
    Route::get('/about', [ContentManagementController::class, 'getAbout']);
    Route::get('/mitra', [ContentManagementController::class, 'getMitra']);
    Route::get('/footer', [ContentManagementController::class, 'getFooter']);
    
    // Kategori Artikel (Public Read)
    Route::get('/artikel/kategori', [KategoriArtikelController::class, 'index']);

    // Ulasan (Public Read)
    Route::get('/ulasan', [UlasanController::class, 'indexPublic']);

    // Hubungi Kami (Public Read & Submit Pesan)
    Route::get('/hubungi-kami', [HubungiKamiController::class, 'getContentPublic']);
    Route::post('/hubungi-kami/kirim-pesan', [HubungiKamiController::class, 'kirimPesan']);
});

// Authenticated Patient / User routes for Ulasan Submission
Route::middleware(['auth:sanctum'])->prefix('resource/content')->group(function () {
    Route::get('/ulasan/user-info', [UlasanController::class, 'userInfo']);
    Route::post('/ulasan', [UlasanController::class, 'storePublic']);
});

// Admin routes (Requires Authentication and Admin Role)
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('resource/content')->group(function () {
    Route::post('/home', [ContentManagementController::class, 'updateHome']);
    Route::post('/about', [ContentManagementController::class, 'updateAbout']);
    Route::post('/mitra', [ContentManagementController::class, 'updateMitra']);
    Route::post('/footer', [ContentManagementController::class, 'updateFooter']);
    Route::post('/ulasan/header', [ContentManagementController::class, 'updateUlasanContent']);

    // Kategori Artikel CRUD (Admin only)
    Route::post('/artikel/kategori', [KategoriArtikelController::class, 'store']);
    Route::get('/artikel/kategori/{id}', [KategoriArtikelController::class, 'show']);
    Route::put('/artikel/kategori/{id}', [KategoriArtikelController::class, 'update']);
    Route::delete('/artikel/kategori/{id}', [KategoriArtikelController::class, 'destroy']);
});
