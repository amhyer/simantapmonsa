<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DapodikController;
use App\Http\Controllers\Api\DapodikSyncController;
use App\Http\Controllers\Api\SchoolLookupController;

// Legacy Dapodik sync (multi-module, for Bridge)
Route::middleware(['apikey:dapodik:import', 'throttle:120,1'])->prefix('dapodik/sync')->group(function () {
    Route::post('/{modul}', [DapodikSyncController::class, 'store']);
    Route::get('/status', [DapodikSyncController::class, 'status']);
});

// Legacy ping
Route::post('/dapodik/ping', [DapodikController::class, 'ping'])->middleware('throttle:60,1');

// School lookup (Fazriansyah API)
Route::post('/sekolah/lookup', [SchoolLookupController::class, 'lookup'])->middleware('throttle:30,1');
