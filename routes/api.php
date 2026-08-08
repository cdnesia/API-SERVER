<?php

use App\Http\Controllers\Api\Auth\TokenController;
use App\Http\Controllers\Api\FeederController;
use App\Http\Controllers\Api\KhsController;
use App\Http\Controllers\Api\MahasiswaController;
use App\Http\Controllers\Api\KrsController;
use App\Http\Controllers\Api\LaporanNilaiController;
use App\Http\Controllers\Api\PegawaiController;
use App\Http\Controllers\Api\PembayaranController;
use App\Http\Controllers\Api\ProdiController;
use App\Http\Controllers\Api\TagihanController;
use App\Http\Controllers\Api\TelegramController;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    return ApiResponse::success(['status' => 'ok']);
});

Route::get('/error', function (Request $request) {
    return ApiResponse::error('API is not running', null, 500, 500100);
});

Route::post('/oauth/token', [TokenController::class, 'issue']);

Route::middleware('jwt.auth')->group(function () {
    Route::prefix('khs')->middleware('scope:khs:read')->group(function () {
        Route::post('/cetak', [KhsController::class, 'cetak']);
    });

    Route::prefix('krs')->middleware('scope:krs:read')->group(function () {
        Route::post('/cetak', [KrsController::class, 'cetak']);
    });

    Route::prefix('tagihan')->group(function () {
        Route::post('/', [TagihanController::class, 'index'])
            ->middleware('scope:tagihan:read,tagihan:index');

        Route::post('/summary', [TagihanController::class, 'summary'])
            ->middleware('scope:tagihan:read,tagihan:summary');

        Route::post('/detail', [TagihanController::class, 'detail'])
            ->middleware('scope:tagihan:read,tagihan:detail');

        Route::post('/cek-lunas', [TagihanController::class, 'cekLunas'])
            ->middleware('scope:tagihan:read,tagihan:cek-lunas');

        Route::post('/massal', [TagihanController::class, 'massal'])
            ->middleware('scope:tagihan:read,tagihan:massal');

        Route::post('/edit', [TagihanController::class, 'edit'])
            ->middleware('scope:tagihan:write,tagihan:edit');

        Route::post('/create-pmb', [TagihanController::class, 'createPMB'])
            ->middleware('scope:tagihan:write,tagihan:create-pmb');
    });

    Route::prefix('pembayaran')->group(function () {
        Route::post('/', [PembayaranController::class, 'index'])
            ->middleware('scope:pembayaran:read,pembayaran:index');

        Route::post('/detail', [PembayaranController::class, 'detail'])
            ->middleware('scope:pembayaran:read,pembayaran:detail');

        Route::post('/by-tagihan', [PembayaranController::class, 'byTagihan'])
            ->middleware('scope:pembayaran:read,pembayaran:by-tagihan');

        Route::post('/by-nomor-tagihan', [PembayaranController::class, 'byNomorTagihan'])
            ->middleware('scope:pembayaran:read,pembayaran:by-nomor-tagihan');

        Route::post('/summary', [PembayaranController::class, 'summary'])
            ->middleware('scope:pembayaran:read,pembayaran:summary');

        Route::post('/by-date-range', [PembayaranController::class, 'byDateRange'])
            ->middleware('scope:pembayaran:read,pembayaran:by-date-range');
    });

    Route::prefix('telegram')->group(function () {
        Route::post('/send-message', [TelegramController::class, 'sendMessage'])
            ->middleware('scope:telegram:read,telegram:send-message');

        Route::post('/send-photo', [TelegramController::class, 'sendPhoto'])
            ->middleware('scope:telegram:read,telegram:send-photo');

        Route::post('/send-document', [TelegramController::class, 'sendDocument'])
            ->middleware('scope:telegram:read,telegram:send-document');

        Route::post('/broadcast', [TelegramController::class, 'broadcast'])
            ->middleware('scope:telegram:read,telegram:broadcast');
    });

    Route::prefix('pegawai')->middleware('scope:pegawai:read')->group(function () {
        Route::post('/', [PegawaiController::class, 'index']);
    });

    Route::prefix('mahasiswa')->middleware('scope:mahasiswa:read')->group(function () {
        Route::post('/', [MahasiswaController::class, 'index']);
        Route::post('/detail', [MahasiswaController::class, 'show']);
    });

    Route::prefix('prodi')->middleware('scope:prodi:read')->group(function () {
        Route::post('/', [ProdiController::class, 'index']);
    });

    Route::prefix('feeder')->middleware('scope:feeder:read')->group(function () {
        Route::post('/cari-by-npm', [FeederController::class, 'cariByNpm']);
        Route::post('/mahasiswa-akm', [FeederController::class, 'mahasiswaAkm']);
        Route::post('/mahasiswa-keluar', [FeederController::class, 'mahasiswaKeluar']);
        Route::post('/mahasiswa-masuk', [FeederController::class, 'mahasiswaMasuk']);
        Route::post('/wilayah', [FeederController::class, 'dataWilayah']);
    });

    Route::prefix('laporan')->group(function () {
        Route::post('/input-nilai', [LaporanNilaiController::class, 'index'])
            ->middleware('scope:laporan:read,laporan:input-nilai');
    });
});
