<?php

use Illuminate\Support\Facades\Route;
use Src\Identity\Infrastructure\Controllers\AuthController;

Route::prefix('v1')->group(function () {
    // Auth
    Route::post('auth/login', [AuthController::class , 'login']);

    // Protected Routes (Endpoints for step 5)
    Route::middleware('auth:api')->group(function () {
        // ... here we will place the next routes
        }
        );    });
