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
            $request->validate([
                'kode_program_studi' => 'nullable|string',
                'tahun_angkatan' => 'nullable|string',
                'program_kuliah' => 'nullable|string',
            ]);

            $query = Mahasiswa::query();

            if ($request->filled('kode_program_studi')) {
                $query->where('kode_program_studi', $request->input('kode_program_studi'));
            }

            if ($request->filled('tahun_angkatan')) {
                $query->where('tahun_angkatan', $request->input('tahun_angkatan'));
            }

            if ($request->filled('program_kuliah')) {
                $query->where('program_kuliah_id', $request->input('program_kuliah_id'));
            }

            $mahasiswa = $query->get();

            return ApiResponse::success($mahasiswa);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validasi gagal', $e->errors(), 422, ErrorCode::VALIDATION_FAILED);
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
