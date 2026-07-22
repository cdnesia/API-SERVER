<?php

use App\Http\Controllers\Api\Auth\TokenController;
use App\Http\Controllers\Api\KhsController;
use App\Http\Controllers\Api\KrsController;
use App\Http\Controllers\Api\TagihanController;
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
    Route::prefix('khs')->group(function () {
        Route::post('/cetak', [KhsController::class, 'cetak']);
    });

    Route::prefix('krs')->group(function () {
        Route::post('/cetak', [KrsController::class, 'cetak']);
    });

    Route::prefix('tagihan')->group(function () {
        Route::post('/', [TagihanController::class, 'index']);
        Route::post('/summary', [TagihanController::class, 'summary']);
        Route::post('/detail', [TagihanController::class, 'detail']);
        Route::post('/cek-lunas', [TagihanController::class, 'cekLunas']);
    });
});
