<?php

use App\Http\Controllers\Api\Auth\TokenController;
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
