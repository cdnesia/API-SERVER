<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PegawaiController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $pegawaraw = Pegawai::all();

        try {
            return ApiResponse::success(
                $pegawaraw
            );
        } catch (\Throwable $e) {
            return ApiResponse::error('Gagal mengambil data tagihan', null, 500);
        }
    }
}
