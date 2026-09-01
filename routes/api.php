<?php

use App\Http\Controllers\Api\Auth\TokenController;
use App\Http\Controllers\Api\BipotController;
use App\Http\Controllers\Api\TagihanController;
use App\Http\Controllers\Api\TagihanSppController;
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
    Route::prefix('tagihan')->group(function () {
        Route::post('/', [TagihanController::class, 'index'])
            ->middleware('scope:tagihan:read,tagihan:get');

        Route::post('/create', [TagihanController::class, 'create'])
            ->middleware('scope:tagihan:write,tagihan:create');

        Route::post('/update', [TagihanController::class, 'update'])
            ->middleware('scope:tagihan:write,tagihan:update');

        Route::post('/delete', [TagihanController::class, 'delete'])
            ->middleware('scope:tagihan:write,tagihan:delete');
    });

    Route::prefix('tagihan-spp')->group(function () {
        Route::post('/', [TagihanSppController::class, 'preview'])
            ->middleware('scope:tagihan-spp:read,tagihan-spp:preview');

        Route::post('/create', [TagihanSppController::class, 'create'])
            ->middleware('scope:tagihan-spp:write,tagihan-spp:create');
    });

    Route::prefix('bipot')->group(function () {
        Route::post('/', [BipotController::class, 'index'])
            ->middleware('scope:bipot:read,bipot:get');

        Route::post('/create', [BipotController::class, 'create'])
            ->middleware('scope:bipot:write,bipot:create');

        Route::post('/update', [BipotController::class, 'update'])
            ->middleware('scope:bipot:write,bipot:update');

        Route::post('/delete', [BipotController::class, 'delete'])
            ->middleware('scope:bipot:write,bipot:delete');

        Route::prefix('angkatan')->group(function () {
            Route::post('/', [BipotController::class, 'angkatan'])
                ->middleware('scope:bipot:read,bipot:angkatan');

            Route::post('/create', [BipotController::class, 'angkatanCreate'])
                ->middleware('scope:bipot:write,bipot:angkatan-create');

            Route::post('/update', [BipotController::class, 'angkatanUpdate'])
                ->middleware('scope:bipot:write,bipot:angkatan-update');

            Route::post('/delete', [BipotController::class, 'angkatanDelete'])
                ->middleware('scope:bipot:write,bipot:angkatan-delete');
        });

        Route::prefix('rincian')->group(function () {
            Route::post('/', [BipotController::class, 'rincian'])
                ->middleware('scope:bipot:read,bipot:rincian');

            Route::post('/create', [BipotController::class, 'rincianCreate'])
                ->middleware('scope:bipot:write,bipot:rincian-create');

            Route::post('/update', [BipotController::class, 'rincianUpdate'])
                ->middleware('scope:bipot:write,bipot:rincian-update');

            Route::post('/delete', [BipotController::class, 'rincianDelete'])
                ->middleware('scope:bipot:write,bipot:rincian-delete');
        });
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
});
