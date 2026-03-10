<?php

use App\Http\Controllers\Api\V1\Iot\IotController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ── Public: IoT Device Ingest ────────────────────────────────────────────────
// No Sanctum auth — authenticated by X-Device-Token header or demo machine_id
Route::post('iot/ingest',       [IotController::class, 'ingest'])->name('iot.ingest');
Route::post('iot/ingest/batch', [IotController::class, 'ingestBatch'])->name('iot.ingest.batch');

// ── Public: Auth ─────────────────────────────────────────────────────────────
Route::prefix('v1/auth')->name('v1.auth.')->group(function () {
    Route::post('/login',  [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'login'])->name('login');
    Route::post('/logout', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'logout'])
        ->middleware('auth:sanctum')->name('logout');
    Route::get('/me',      [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'me'])
        ->middleware('auth:sanctum')->name('me');
});

// ── Protected V1 API ─────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'factory.scope', 'factory.member'])
    ->prefix('v1')
    ->name('v1.')
    ->group(function () {

        // ── Factory ───────────────────────────────────────────────────────
        Route::apiResource('factories', \App\Http\Controllers\Api\V1\Factory\FactoryController::class);
        Route::get('factories/{factory}/settings',
            [\App\Http\Controllers\Api\V1\Factory\FactoryController::class, 'settings']);
        Route::put('factories/{factory}/settings',
            [\App\Http\Controllers\Api\V1\Factory\FactoryController::class, 'updateSettings']);

        // ── Machine ───────────────────────────────────────────────────────
        Route::apiResource('machines', \App\Http\Controllers\Api\V1\Machine\MachineController::class);
        Route::get('machines/{machine}/logs',
            [\App\Http\Controllers\Api\V1\Machine\MachineController::class, 'logs']);
        Route::post('machines/{machine}/logs',
            [\App\Http\Controllers\Api\V1\Machine\MachineController::class, 'recordLog']);

        // ── Downtime ──────────────────────────────────────────────────────
        Route::apiResource('downtimes', \App\Http\Controllers\Api\V1\Downtime\DowntimeController::class);
        Route::apiResource('downtime-reasons', \App\Http\Controllers\Api\V1\Downtime\DowntimeReasonController::class);

        // ── Customers ─────────────────────────────────────────────────────
        Route::apiResource('customers', \App\Http\Controllers\Api\V1\Production\CustomerController::class);

        // ── Parts ─────────────────────────────────────────────────────────
        Route::apiResource('parts', \App\Http\Controllers\Api\V1\Production\PartController::class);
        Route::put('parts/{part}/processes',
            [\App\Http\Controllers\Api\V1\Production\PartController::class, 'syncProcesses']);

        // ── Process Masters ───────────────────────────────────────────────
        Route::apiResource('process-masters', \App\Http\Controllers\Api\V1\Production\ProcessMasterController::class);
        Route::get('process-masters-palette',
            [\App\Http\Controllers\Api\V1\Production\ProcessMasterController::class, 'palette']);
        Route::post('process-masters/preview-cycle-time',
            [\App\Http\Controllers\Api\V1\Production\ProcessMasterController::class, 'previewCycleTime']);

        // ── Production Plans ──────────────────────────────────────────────
        Route::apiResource('work-orders', \App\Http\Controllers\Api\V1\Production\WorkOrderController::class);

        Route::apiResource('production-plans', \App\Http\Controllers\Api\V1\Production\ProductionPlanController::class);
        Route::get('production-plans/{plan}/analysis',
            [\App\Http\Controllers\Api\V1\Production\ProductionAnalysisController::class, 'planAnalysis']);

        // ── Production Actuals ────────────────────────────────────────────
        Route::apiResource('production-actuals', \App\Http\Controllers\Api\V1\Production\ProductionActualController::class);

        // ── Analytics ─────────────────────────────────────────────────────
        Route::prefix('analytics')->name('analytics.')->group(function () {
            Route::get('parts/{part}/targets',
                [\App\Http\Controllers\Api\V1\Production\ProductionAnalysisController::class, 'partTargets']);
            Route::get('factories/{factory}/daily-targets',
                [\App\Http\Controllers\Api\V1\Production\ProductionAnalysisController::class, 'factoryDailyTargets']);
        });

        // ── Shifts (used by IoT dashboard shift selector) ─────────────────────
        Route::get('shifts', [IotController::class, 'shifts'])->name('shifts');

        // ── IoT Dashboard API ─────────────────────────────────────────────────
        Route::prefix('iot')->name('iot.')->group(function () {
            Route::get('status',                    [IotController::class, 'status'])->name('status');
            Route::get('machines/{machine}/chart',    [IotController::class, 'machineChart'])->name('machine.chart');
            Route::get('machines/{machine}/timeline', [IotController::class, 'machineTimeline'])->name('machine.timeline');
            Route::get('machines/{machine}/export',   [IotController::class, 'machineExport'])->name('machine.export');

            // OEE from IoT pulse data (part_count / part_reject signals)
            Route::get('oee',                    [\App\Http\Controllers\Api\V1\Iot\OeeController::class, 'factoryOee'])->name('oee.factory');
            Route::get('oee/trend',              [\App\Http\Controllers\Api\V1\Iot\OeeController::class, 'oeeTrend'])->name('oee.trend');
            Route::get('machines/{machine}/oee', [\App\Http\Controllers\Api\V1\Iot\OeeController::class, 'machineOee'])->name('machine.oee');
        });
    });
