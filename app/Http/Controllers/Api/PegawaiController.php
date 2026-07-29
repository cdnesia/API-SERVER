<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PegawaiController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $pegawaraw = Pegawai::all();
            return ApiResponse::success($pegawaraw);    
        } catch (\Throwable $e) {
            Log::error('PegawaiController: gagal mengambil data pegawai.', ['message' => $e->getMessage()]);
            return ApiResponse::error('Gagal mengambil data pegawai', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }
}
