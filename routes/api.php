<?php

use App\Http\Controllers\Api\Auth\TokenController;
use App\Http\Controllers\Api\KhsController;
use App\Http\Controllers\Api\KrsController;
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
    });

    // ── Telegram Notification ──
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
});
