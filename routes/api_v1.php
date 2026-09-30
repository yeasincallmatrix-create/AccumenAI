<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\InstituteController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ModuleController;
use App\Http\Controllers\Api\V1\SyncController;
use Illuminate\Support\Facades\Route;

/*
 * Versioned mobile surface (/api/v1/*).
 * Loaded from bootstrap/app.php `then:` — routes/api.php stays untouched.
 * Auth stack reuses the proven web-adjacent guards:
 *   auth:sanctum + ensure.institute.context (TenantContext + status check)
 *   force.json (Accept: application/json) + throttle:60,1 (per token/user).
 */
Route::prefix('v1')->middleware(['force.json'])->group(function () {
    Route::get('health', function () {
        return \App\Http\Responses\ApiResponse::success([
            'status' => 'ok',
            'ts' => now()->toISOString(),
        ]);
    });

    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');
    Route::post('auth/refresh', [AuthController::class, 'refresh'])
        ->middleware('auth:sanctum');

    Route::middleware(['auth:sanctum', 'ensure.institute.context', 'throttle:60,1'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('me', [MeController::class, 'show']);
        Route::patch('me', [MeController::class, 'update']);

        Route::get('institutes', [InstituteController::class, 'index']);
        Route::get('institutes/{id}/modules', [ModuleController::class, 'index']);
        Route::get('modules', [ModuleController::class, 'all']);

        Route::get('sync/delta', [SyncController::class, 'delta']);
        Route::post('sync/push', [SyncController::class, 'push'])
            ->middleware('idempotency');

        Route::post('devices/register', [DeviceController::class, 'register'])
            ->middleware('idempotency');
        Route::delete('devices/{token}', [DeviceController::class, 'unregister']);
    });
});
