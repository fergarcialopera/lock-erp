<?php

use Illuminate\Support\Facades\Route;
use Src\Identity\Infrastructure\Controllers\AuthController;
use Src\Identity\Infrastructure\Controllers\UserController;
use Src\Inventory\Infrastructure\Controllers\InventoryController;
use Src\Inventory\Infrastructure\Controllers\ProductController;
use Src\Lockers\Infrastructure\Controllers\ClinicController;
use Src\Lockers\Infrastructure\Controllers\LockerController;
use Src\Lockers\Infrastructure\Controllers\CompartmentController;
use Src\OpenOrders\Infrastructure\Controllers\OpenOrderController;
use Src\Audit\Infrastructure\Controllers\AuditController;
use Src\Dashboard\Infrastructure\Controllers\DashboardController;

Route::prefix('v1')->group(function () {
    // Auth (público)
    Route::post('auth/login', [AuthController::class, 'login']);

    // Protected Routes
    Route::middleware('auth:api')->group(function () {
        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Clinic
        Route::get('clinic', [ClinicController::class, 'getClinic']);
        Route::patch('clinic/settings', [ClinicController::class, 'updateSettings']);

        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index']);

        // Products
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{id}', [ProductController::class, 'show']);
        Route::post('products', [ProductController::class, 'store']);
        Route::patch('products/{id}', [ProductController::class, 'update']);
        Route::delete('products/{id}', [ProductController::class, 'destroy']);

        // Users
        Route::get('users', [UserController::class, 'index']);
        Route::get('users/{id}', [UserController::class, 'show']);
        Route::post('users', [UserController::class, 'store']);
        Route::patch('users/{id}', [UserController::class, 'update']);
        Route::delete('users/{id}', [UserController::class, 'destroy']);

        // Lockers
        Route::get('lockers', [LockerController::class, 'index']);
        Route::get('lockers/{id}/compartments', [CompartmentController::class, 'indexByLocker']);
        Route::get('lockers/{id}', [LockerController::class, 'show']);
        Route::post('lockers', [LockerController::class, 'store']);
        Route::patch('lockers/{id}', [LockerController::class, 'update']);
        Route::delete('lockers/{id}', [LockerController::class, 'destroy']);

        // Compartments
        Route::get('compartments', [CompartmentController::class, 'index']);
        Route::get('compartments/{id}', [CompartmentController::class, 'show']);
        Route::post('compartments', [CompartmentController::class, 'store']);
        Route::patch('compartments/{id}', [CompartmentController::class, 'update']);
        Route::delete('compartments/{id}', [CompartmentController::class, 'destroy']);

        // Inventory
        Route::get('inventory', [InventoryController::class, 'index']);
        Route::post('inventory/adjust', [InventoryController::class, 'adjust']);
        Route::post('inventory/add', [InventoryController::class, 'add']);
        Route::post('inventory/remove', [InventoryController::class, 'remove']);
        Route::delete('inventory/{id}', [InventoryController::class, 'destroy']);

        // Open Orders (las órdenes solo se crean al retirar desde inventario: POST /inventory/remove)
        Route::get('open-orders', [OpenOrderController::class, 'index']);
        Route::post('open-orders/{id}/confirm-read', [OpenOrderController::class, 'confirmRead']);

        // Audit Logs
        Route::get('audit-logs', [AuditController::class, 'index']);
    });
});
