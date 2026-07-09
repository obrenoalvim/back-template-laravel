<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// Notes and health routes are added in later tasks.

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/resend', [AuthController::class, 'resendVerification'])
        ->middleware(['auth:sanctum', 'throttle:6,1']);

    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');
});

Route::prefix('account')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [AccountController::class, 'show']);
    Route::put('/password', [AccountController::class, 'changePassword']);
    Route::delete('/', [AccountController::class, 'destroy']);
});
