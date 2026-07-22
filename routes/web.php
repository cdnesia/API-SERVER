<?php

use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::any('{any}', function () {
    return ApiResponse::error('No route found', code: 404);
})->where('any', '^(?!api(?:/|$)).*$');
