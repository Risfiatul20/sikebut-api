<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\IdentifikasiKebutuhanController;
use App\Http\Controllers\Api\RefAkunController;
use App\Http\Controllers\Api\RefProgramController;
use App\Http\Controllers\Api\RefSkpdController;
use App\Http\Controllers\Api\SipdPenetapanApbdController;
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
        Route::get('ref-akun/view', [RefAkunController::class, 'view']);
        Route::get('ref-akun', [RefAkunController::class, 'index']);
        Route::get('ref-program', [RefProgramController::class, 'programs']);
        Route::get('ref-kegiatan', [RefProgramController::class, 'kegiatans']);
        Route::get('ref-sub-kegiatan', [RefProgramController::class, 'subKegiatans']);
        Route::get('identifikasi-kebutuhan', [IdentifikasiKebutuhanController::class, 'index']);
        Route::post('identifikasi-kebutuhan', [IdentifikasiKebutuhanController::class, 'store']);
        Route::get('identifikasi-kebutuhan/{id}', [IdentifikasiKebutuhanController::class, 'show'])->whereNumber('id');
        Route::put('identifikasi-kebutuhan/{id}', [IdentifikasiKebutuhanController::class, 'update'])->whereNumber('id');
        Route::delete('identifikasi-kebutuhan/{id}', [IdentifikasiKebutuhanController::class, 'destroy'])->whereNumber('id');
        Route::get('sipd-penetapan-apbd/modal', [SipdPenetapanApbdController::class, 'modal']);
        Route::get('sipd-penetapan-apbd', [SipdPenetapanApbdController::class, 'index']);
        Route::get('sipd-penetapan-apbd/{id}', [SipdPenetapanApbdController::class, 'show'])->whereNumber('id');
    });
});
