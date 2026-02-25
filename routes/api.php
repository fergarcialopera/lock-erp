<?php

use Illuminate\Support\Facades\Route;
use Src\Identity\Infrastructure\Controllers\AuthController;

Route::prefix('v1')->group(function () {
    // Auth
    Route::post('auth/login', [AuthController::class , 'login']);

    // Protected Routes
    Route::middleware('auth:api')->group(function () {
            // Clinic
            Route::get('clinic', [\Src\Lockers\Infrastructure\Controllers\ClinicController::class , 'getClinic']);
            Route::patch('clinic/settings', [\Src\Lockers\Infrastructure\Controllers\ClinicController::class , 'updateSettings']);

            // Inventory
            Route::get('inventory', [\Src\Inventory\Infrastructure\Controllers\InventoryController::class , 'index']);
            Route::post('inventory/adjust', [\Src\Inventory\Infrastructure\Controllers\InventoryController::class , 'adjust']);

            // Open Orders
            Route::get('open-orders', [\Src\OpenOrders\Infrastructure\Controllers\OpenOrderController::class , 'index']);
            Route::post('open-orders', [\Src\OpenOrders\Infrastructure\Controllers\OpenOrderController::class , 'create']);
            Route::post('open-orders/{id}/confirm-read', [\Src\OpenOrders\Infrastructure\Controllers\OpenOrderController::class , 'confirmRead']);

            // Audit Logs
            Route::get('audit-logs', [\Src\Audit\Infrastructure\Controllers\AuditController::class , 'index']);
        }
        );    });
