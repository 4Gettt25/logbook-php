<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\MetricController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Agent Management API
Route::prefix('agents')->group(function () {
    Route::post('register', [AgentController::class, 'register']);
    Route::post('{agent}/heartbeat', [AgentController::class, 'heartbeat']);
    Route::get('{agent}/config', [AgentController::class, 'getConfig']);
});

// Log Ingestion API
Route::prefix('logs')->group(function () {
    Route::post('ingest', [LogController::class, 'ingest']);
    Route::post('batch', [LogController::class, 'batchIngest']);
});

// Metrics Ingestion API
Route::prefix('metrics')->group(function () {
    Route::post('ingest', [MetricController::class, 'ingest']);
    Route::post('batch', [MetricController::class, 'batchIngest']);
});