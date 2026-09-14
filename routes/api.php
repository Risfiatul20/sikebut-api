<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\IdentifikasiKebutuhanController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\LaporanController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RefAkunController;
use App\Http\Controllers\Api\RefProgramController;
use App\Http\Controllers\Api\RefSkpdController;
use App\Http\Controllers\Api\RkbmdPemeliharaanController;
use App\Http\Controllers\Api\RkbmdPengadaanController;
use App\Http\Controllers\Api\SipdPenetapanApbdController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserSubKegiatanController;
use App\Http\Controllers\Api\WaGatewayController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::put('me', [AuthController::class, 'updateMe']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    // Callback dari WA Gateway (Node.js) — autentikasi via X-Gateway-Key, bukan Sanctum.
    Route::post('wa-gateway/callback', [WaGatewayController::class, 'callback']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('wa-gateway', [WaGatewayController::class, 'index']);
        Route::post('wa-gateway/devices', [WaGatewayController::class, 'store']);
        Route::get('wa-gateway/devices/{deviceId}/qr', [WaGatewayController::class, 'qr']);
        Route::post('wa-gateway/devices/{deviceId}/logout', [WaGatewayController::class, 'logout']);
        Route::post('wa-gateway/devices/{deviceId}/priority', [WaGatewayController::class, 'setPriority']);
        Route::delete('wa-gateway/devices/{deviceId}', [WaGatewayController::class, 'destroy']);
        Route::post('wa-gateway/test-send', [WaGatewayController::class, 'testSend']);
        Route::get('wa-gateway/messages', [WaGatewayController::class, 'messages']);

        Route::get('user-sub-kegiatan', [UserSubKegiatanController::class, 'index']);
        Route::get('user-sub-kegiatan/ppk-users', [UserSubKegiatanController::class, 'ppkUsers']);
        Route::post('user-sub-kegiatan', [UserSubKegiatanController::class, 'store']);
        Route::delete('user-sub-kegiatan/{id}', [UserSubKegiatanController::class, 'destroy'])->whereNumber('id');
        Route::get('dashboard/summary', [DashboardController::class, 'summary']);
        Route::get('dashboard/keterisian-ppk', [DashboardController::class, 'keterisianPpk']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->whereNumber('id');
        Route::apiResource('users', UserController::class);
        Route::get('ref-skpd', [RefSkpdController::class, 'index']);
        Route::get('ref-akun/view', [RefAkunController::class, 'view'])->middleware('cache.client:300');
        Route::get('ref-akun', [RefAkunController::class, 'index']);
        Route::get('ref-program', [RefProgramController::class, 'programs'])->middleware('cache.client:3600');
        Route::get('ref-kegiatan', [RefProgramController::class, 'kegiatans']);
        Route::get('ref-sub-kegiatan', [RefProgramController::class, 'subKegiatans']);
        Route::get('identifikasi-kebutuhan', [IdentifikasiKebutuhanController::class, 'index']);
        Route::post('identifikasi-kebutuhan', [IdentifikasiKebutuhanController::class, 'store']);
        Route::get('identifikasi-kebutuhan/{id}', [IdentifikasiKebutuhanController::class, 'show'])->whereNumber('id');
        Route::put('identifikasi-kebutuhan/{id}', [IdentifikasiKebutuhanController::class, 'update'])->whereNumber('id');
        Route::delete('identifikasi-kebutuhan/{id}', [IdentifikasiKebutuhanController::class, 'destroy'])->whereNumber('id');
        Route::post('identifikasi-kebutuhan/{id}/submit', [IdentifikasiKebutuhanController::class, 'submit'])->whereNumber('id');
        Route::post('identifikasi-kebutuhan/{id}/verify', [IdentifikasiKebutuhanController::class, 'verify'])->whereNumber('id');
        Route::post('identifikasi-kebutuhan/{id}/return', [IdentifikasiKebutuhanController::class, 'returnForRevision'])->whereNumber('id');
        Route::post('identifikasi-kebutuhan/{id}/note', [IdentifikasiKebutuhanController::class, 'note'])->whereNumber('id');
        Route::get('identifikasi-kebutuhan/{id}/riwayat', [IdentifikasiKebutuhanController::class, 'riwayat'])->whereNumber('id');
        Route::get('sipd-versions', [SipdPenetapanApbdController::class, 'versions']);
        Route::get('sipd-penetapan-apbd/modal', [SipdPenetapanApbdController::class, 'modal']);
        Route::get('sipd-penetapan-apbd', [SipdPenetapanApbdController::class, 'index']);
        Route::get('sipd-penetapan-apbd/{id}', [SipdPenetapanApbdController::class, 'show'])->whereNumber('id');

        Route::get('rkbmd-pengadaan', [RkbmdPengadaanController::class, 'index']);
        Route::post('rkbmd-pengadaan/manual', [RkbmdPengadaanController::class, 'store']);
        Route::get('rkbmd-pengadaan/{id}', [RkbmdPengadaanController::class, 'show'])->whereNumber('id');

        Route::get('rkbmd-pemeliharaan', [RkbmdPemeliharaanController::class, 'index']);
        Route::post('rkbmd-pemeliharaan/manual', [RkbmdPemeliharaanController::class, 'store']);
        Route::get('rkbmd-pemeliharaan/{id}', [RkbmdPemeliharaanController::class, 'show'])->whereNumber('id');

        Route::get('laporan/rekap', [LaporanController::class, 'rekap']);
        Route::get('laporan/rekap/export', [LaporanController::class, 'rekapExport']);
        Route::get('laporan/kebutuhan', [LaporanController::class, 'kebutuhan']);
        Route::get('laporan/kebutuhan/export', [LaporanController::class, 'kebutuhanExport']);
        Route::get('laporan/penyedia', [LaporanController::class, 'penyedia']);
        Route::get('laporan/penyedia/export', [LaporanController::class, 'paketPerCaraExport'])->defaults('cara', 'Penyedia');
        Route::get('laporan/swakelola', [LaporanController::class, 'swakelola']);
        Route::get('laporan/swakelola/export', [LaporanController::class, 'paketPerCaraExport'])->defaults('cara', 'Swakelola');
        Route::get('laporan/ba-pembahasan-penyedia', [LaporanController::class, 'baPembahasan'])->defaults('cara', 'Penyedia');
        Route::get('laporan/ba-pembahasan-penyedia/export', [LaporanController::class, 'baPembahasanExport'])->defaults('cara', 'Penyedia');
        Route::get('laporan/ba-pembahasan-swakelola', [LaporanController::class, 'baPembahasan'])->defaults('cara', 'Swakelola');
        Route::get('laporan/ba-pembahasan-swakelola/export', [LaporanController::class, 'baPembahasanExport'])->defaults('cara', 'Swakelola');
        Route::get('laporan/ba-rkbmd-pengadaan', [LaporanController::class, 'baRkbmd'])->defaults('tipe', 'pengadaan');
        Route::get('laporan/ba-rkbmd-pengadaan/export', [LaporanController::class, 'baRkbmdExport'])->defaults('tipe', 'pengadaan');
        Route::get('laporan/ba-rkbmd-pemeliharaan', [LaporanController::class, 'baRkbmd'])->defaults('tipe', 'pemeliharaan');
        Route::get('laporan/ba-rkbmd-pemeliharaan/export', [LaporanController::class, 'baRkbmdExport'])->defaults('tipe', 'pemeliharaan');

        Route::prefix('import')->group(function () {
            Route::post('rkbmd-pengadaan', [ImportController::class, 'importRkbmdPengadaan']);
            Route::post('rkbmd-pemeliharaan', [ImportController::class, 'importRkbmdPemeliharaan']);
            Route::post('sipd-penetapan-apbd', [ImportController::class, 'importSipdPenetapanApbd']);
            Route::get('status/{id}', [ImportController::class, 'checkStatus']);
        });
    });

});
