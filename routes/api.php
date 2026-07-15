<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FarmerController;
use App\Http\Controllers\FarmPlotController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\DistributionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BroadcastController;
use App\Http\Controllers\IntelligenceController;
use App\Http\Controllers\DamageAssessmentController;
use App\Http\Controllers\PcicEnrollmentController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\ReportWorkflowController;
use App\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

// ── Public ────────────────────────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);

// ── Authenticated ─────────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Farmer Registry
    Route::get('/farmers', [FarmerController::class, 'index']);
    Route::post('/farmers', [FarmerController::class, 'store']);
    Route::get('/farmers/lookup', [FarmerController::class, 'lookup']);
    Route::get('/farmers/barangays', [FarmerController::class, 'barangays']);
    Route::get('/farmers/commodities', [FarmerController::class, 'commodities']);
    Route::get('/farmers/{id}', [FarmerController::class, 'show']);
    Route::post('/farmers/{id}/photo', [FarmerController::class, 'uploadPhoto'])
        ->middleware('role:admin');

    // Farm Plots
    Route::get('/farm-plots', [FarmPlotController::class, 'index']);
    Route::get('/farm-plots/{id}', [FarmPlotController::class, 'show']);

    // Subsidy Programs
    Route::get('/programs', [ProgramController::class, 'index']);
    Route::get('/programs/{id}', [ProgramController::class, 'show']);
    Route::post('/programs', [ProgramController::class, 'store'])
        ->middleware('role:admin');
    Route::patch('/programs/{id}/deactivate', [ProgramController::class, 'deactivate'])
        ->middleware('role:admin');
    Route::post('/programs/{id}/restock', [ProgramController::class, 'restock'])
        ->middleware('role:admin');
    Route::patch('/programs/{id}/config', [ProgramController::class, 'updateConfig'])
        ->middleware('role:admin');

    // Distribution / Claiming
    Route::post('/distributions/verify', [DistributionController::class, 'verify']);
    Route::post('/distributions/claim', [DistributionController::class, 'processClaim']);

    // Offline Bulk Sync
    Route::post('/sync/bulk', [SyncController::class, 'bulkUpload']);

    // Analytics & Reports
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
    Route::get('/dashboard/map-data', [DashboardController::class, 'mapData'])
        ->middleware('role:admin,technician');
    Route::get('/dashboard/forecast', [DashboardController::class, 'forecast'])
        ->middleware('role:admin');
    Route::get('/dashboard/risk-index', [DashboardController::class, 'riskIndex'])
        ->middleware('role:admin');
    Route::get('/dashboard/report', [DashboardController::class, 'accomplishmentReport'])
        ->middleware('role:admin');
    Route::get('/reports/export/{type}', [ReportExportController::class, 'export'])
        ->middleware('role:admin');

    // Statutory Report Workflows
    Route::post('/report-workflows/preview', [ReportWorkflowController::class, 'preview'])
        ->middleware('role:admin');
    Route::get('/report-workflows', [ReportWorkflowController::class, 'index'])
        ->middleware('role:admin');
    Route::post('/report-workflows', [ReportWorkflowController::class, 'store'])
        ->middleware('role:admin');
    Route::get('/report-workflows/{id}', [ReportWorkflowController::class, 'show'])
        ->middleware('role:admin');
    Route::patch('/report-workflows/{id}/verify', [ReportWorkflowController::class, 'verify'])
        ->middleware('role:admin');
    Route::patch('/report-workflows/{id}/finalize', [ReportWorkflowController::class, 'finalize'])
        ->middleware('role:admin');

    // Technician personal contribution history
    Route::get('/technician/activity-log', [DashboardController::class, 'activityLog'])
        ->middleware('role:technician');

    // SMS Broadcast
    Route::get('/broadcasts', [BroadcastController::class, 'index']);
    Route::post('/broadcasts/send', [BroadcastController::class, 'sendBulkSms'])
        ->middleware('role:admin');

    // Agricultural Intelligence
    Route::post('/intelligence/crop-log', [IntelligenceController::class, 'logCrop']);
    Route::get('/intelligence/dashboard', [IntelligenceController::class, 'getDashboardData']);
    Route::post('/intelligence/pest-report', [IntelligenceController::class, 'reportPest']);
    Route::get('/intelligence/crop-history', [IntelligenceController::class, 'cropHistory']);
    Route::get('/intelligence/monoculture-alerts', [IntelligenceController::class, 'monocultureAlerts'])
        ->middleware('role:admin');
    Route::patch('/intelligence/pest-outbreaks/{id}/status', [IntelligenceController::class, 'updatePestStatus'])
        ->middleware('role:admin,technician');
    Route::post('/intelligence/pest-outbreaks/{id}/advisory', [IntelligenceController::class, 'broadcastAdvisory'])
        ->middleware('role:admin');

    // Disaster Damage Assessment Workflow
    Route::get('/damage-assessments', [DamageAssessmentController::class, 'index']);
    Route::post('/damage-assessments', [DamageAssessmentController::class, 'store']);
    Route::get('/damage-assessments/{id}', [DamageAssessmentController::class, 'show']);
    Route::patch('/damage-assessments/{id}/verify', [DamageAssessmentController::class, 'verify'])
        ->middleware('role:barangay_official,admin');
    Route::patch('/damage-assessments/{id}/decide', [DamageAssessmentController::class, 'decide'])
        ->middleware('role:admin');
    Route::patch('/damage-assessments/{id}/file-notice', [DamageAssessmentController::class, 'fileNotice'])
        ->middleware('role:admin');
    Route::get('/damage-assessments/{id}/notice', [DamageAssessmentController::class, 'noticeData'])
        ->middleware('role:admin');

    // PCIC Crop Insurance Enrollments
    Route::get('/pcic-enrollments', [PcicEnrollmentController::class, 'index'])
        ->middleware('role:admin');
    Route::post('/pcic-enrollments', [PcicEnrollmentController::class, 'store'])
        ->middleware('role:admin');
    Route::patch('/pcic-enrollments/{id}', [PcicEnrollmentController::class, 'update'])
        ->middleware('role:admin');
    Route::get('/pcic-enrollments/export', [PcicEnrollmentController::class, 'export'])
        ->middleware('role:admin');
});
