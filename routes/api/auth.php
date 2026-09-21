<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CoreAuthController;
use App\Http\Controllers\GooglePasienController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\ForgotPasswordController;

Route::post('/register', [CoreAuthController::class, 'register']);
Route::post('/login', [CoreAuthController::class, 'login']);
Route::post('/googleAuth', [GooglePasienController::class, 'handleGoogleCallback']);

// Forgot Password - Pasien / Nakes
Route::post('/forgot-password', [ForgotPasswordController::class, 'requestOtpUser']);
Route::post('/forgot-password/verify', [ForgotPasswordController::class, 'verifyOtpUser']);
Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'resetPasswordUser']);

// Admin Auth & Forgot Password
Route::post('/admin/login', [AdminAuthController::class, 'login']);
Route::post('/admin/forgot-password', [ForgotPasswordController::class, 'requestOtpAdmin']);
Route::post('/admin/forgot-password/verify', [ForgotPasswordController::class, 'verifyOtpAdmin']);
Route::post('/admin/forgot-password/reset', [ForgotPasswordController::class, 'resetPasswordAdmin']);

//verifikasi register 
Route::get('/email/verify/{id}/{hash}', [CoreAuthController::class, 'verifyEmail'])
    ->name('verification.verify')
    ->middleware(['signed']);

Route::post('/email/resend', [CoreAuthController::class, 'resendVerificationEmail']);
Route::post('/change-unverified-email', [CoreAuthController::class, 'changeUnverifiedEmail']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

