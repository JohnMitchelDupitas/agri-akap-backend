<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FarmerController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\DistributionController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::post('/login', [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Core Registry Endpoints
    Route::get('/farmers', [FarmerController::class, 'index']);
    Route::post('/farmers', [FarmerController::class, 'store']);

    // Subsidy & Inventory Control Systems
    Route::get('/programs', [ProgramController::class, 'index']);
    Route::post('/programs', [ProgramController::class, 'store']);
    Route::get('/programs/{id}', [ProgramController::class, 'show']);

    // Mobile Verification & Claiming Engine
    Route::post('/distributions/claim', [DistributionController::class, 'processClaim']);

    // Analytics & Accomplishment Reports
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
});
