<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LayananController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\ArtikelController;
use App\Http\Controllers\KategoriArtikelController;
use App\Http\Controllers\PasienController;
use App\Http\Controllers\KategoriLayananController;
use App\Http\Controllers\WilayahLayananController;
use App\Http\Controllers\KotaKabupatenController;
use App\Http\Controllers\KategoriPembayaranController;
use App\Http\Controllers\MetodePembayaranController;
use App\Http\Controllers\MasterUniversitasController;
use App\Http\Controllers\TagController;

Route::get('/layanan', [LayananController::class, 'index']);
Route::get('/layanan/kategori', [KategoriLayananController::class, 'index']);
Route::get('/layanan/{layanan}', [LayananController::class, 'show']);

Route::get('/promo', [PromoController::class, 'index']);
Route::get('/promo/active', [PromoController::class, 'getActivePromos']);
Route::get('/promo/{promo}', [PromoController::class, 'show']);

Route::get('/artikel', [ArtikelController::class, 'index']);
Route::get('/artikel/{artikel}', [ArtikelController::class, 'show']);

Route::get('/tags', [TagController::class, 'index']);
Route::get('/tags/{id}', [TagController::class, 'show']);

Route::get('/pasien', [PasienController::class, 'index']);

//Provinsi
Route::get('/provinsi', [WilayahLayananController::class, 'index']);
Route::get('/wilayah-layanan', [WilayahLayananController::class, 'index']);
Route::get('/wilayah-layanan/{wilayahLayanan}', [WilayahLayananController::class, 'show']);


//Kota Kabupaten
Route::get('/kota-kabupaten', [KotaKabupatenController::class, 'index']);
Route::get('/kota-kabupaten/provinsi/{id_provinsi}', [KotaKabupatenController::class, 'getByProvinsi']);
Route::get('/kota-kabupaten/{id}', [KotaKabupatenController::class, 'show']);

// Pembayaran
Route::get('/pembayaran/kategori', [KategoriPembayaranController::class, 'index']);
Route::get('/pembayaran/metode', [MetodePembayaranController::class, 'index']);

// Master Bank (Public - untuk dropdown pemilihan bank)
Route::get('/banks', [\App\Http\Controllers\MasterBankController::class, 'index']);

// Global Config
Route::get('/global-config', [\App\Http\Controllers\GlobalConfigController::class, 'getGlobalConfig']);

// SEO Config
Route::get('/seo-config', [\App\Http\Controllers\SeoConfigController::class, 'getSeoConfig']);

// Web Setting (Logo & Favicon)
Route::get('/web-setting', [\App\Http\Controllers\WebSettingController::class, 'getWebSetting']);

// Legality (Syarat & Ketentuan)
Route::get('/legalitas/detail/{key}', [\App\Http\Controllers\LegalityController::class, 'getPublicLegality']);
Route::get('/legalitas/list', [\App\Http\Controllers\LegalityController::class, 'publicList']);

// Master Universitas (Public - untuk dropdown pendaftaran nakes/mitra)
Route::get('/universitas', [MasterUniversitasController::class, 'publicIndex']);
