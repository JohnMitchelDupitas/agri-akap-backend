<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::post('/login', [AuthController::class, 'login']);

// Protected Routes (Requires standard Authorization Bearer Token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Future routes will go here:
    // Route::get('/farmers', [FarmerController::class, 'index']);
    // Route::post('/sync/distributions', [SyncController::class, 'push']);
});
