<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RefAkunController;
use App\Http\Controllers\Api\RefSkpdController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::get('ref-skpd', [RefSkpdController::class, 'index']);
        Route::get('ref-akun', [RefAkunController::class, 'index']);
    });
});
