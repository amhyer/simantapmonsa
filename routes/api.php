<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DapodikController;
use App\Http\Controllers\Api\DapodikSyncController;
use App\Http\Controllers\Api\PresensiMesinSyncController;
use App\Http\Controllers\Api\SchoolLookupController;

// Legacy Dapodik sync (multi-module, for Bridge)
Route::middleware(['apikey:dapodik:import', 'tenant', 'throttle:120,1'])->prefix('dapodik/sync')->group(function () {
    Route::post('/{modul}', [DapodikSyncController::class, 'store']);
    Route::get('/export/{modul}', [DapodikSyncController::class, 'export']);
    Route::get('/status', [DapodikSyncController::class, 'status']);
});

// Legacy ping
Route::post('/dapodik/ping', [DapodikController::class, 'ping'])->middleware('throttle:60,1');

// Presensi mesin dari bridge di sekolah ke website hosting
Route::middleware(['apikey:presensi:import', 'tenant', 'throttle:60,1'])->prefix('presensi/mesin')->group(function () {
    Route::post('/sinkron', [PresensiMesinSyncController::class, 'sinkron']);
    Route::get('/status', [PresensiMesinSyncController::class, 'status']);
});

// School lookup (Fazriansyah API)
Route::post('/sekolah/lookup', [SchoolLookupController::class, 'lookup'])->middleware('throttle:30,1');
