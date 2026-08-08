<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mahasiswa;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MahasiswaController extends Controller
{
    /**
     * Ambil semua data mahasiswa dari connection siade, table master_mahasiswa.
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $mahasiswa = Mahasiswa::all();
            return ApiResponse::success($mahasiswa);
        } catch (\Throwable $e) {
            Log::error('MahasiswaController: gagal mengambil data mahasiswa.', ['message' => $e->getMessage()]);
            return ApiResponse::error('Gagal mengambil data mahasiswa', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Ambil data mahasiswa berdasarkan NPM.
     */
    public function show(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate([
                'npm' => 'required|string',
            ]);

            $mahasiswa = Mahasiswa::where('npm', $request->npm)->first();

            if (! $mahasiswa) {
                return ApiResponse::error('Mahasiswa tidak ditemukan', null, 404, ErrorCode::DATA_NOT_FOUND);
            }

            return ApiResponse::success($mahasiswa);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validasi gagal', $e->errors(), 422, ErrorCode::VALIDATION_FAILED);
        } catch (\Throwable $e) {
            Log::error('MahasiswaController: gagal mengambil detail mahasiswa.', ['message' => $e->getMessage()]);
            return ApiResponse::error('Gagal mengambil data mahasiswa', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }
}
