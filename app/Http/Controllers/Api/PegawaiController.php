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
        // $validator = Validator::make($request->json()->all(), [
        //     'npm'     => ['required', 'string', 'max:20'],
        //     'periode' => ['nullable', 'string', 'regex:/^\d{4}[12]$/'],
        // ], [
        //     'npm.required'  => 'NPM wajib diisi.',
        //     'npm.max'       => 'NPM maksimal :max karakter.',
        //     'periode.regex' => 'Format periode tidak valid. Gunakan format YYYY1 (Ganjil) atau YYYY2 (Genap), contoh: 20241.',
        // ]);

        // if ($validator->fails()) {
        //     return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        // }

        // $npm     = $request->json('npm');
        // $periode = $request->json('periode');
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
