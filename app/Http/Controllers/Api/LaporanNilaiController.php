<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LaporanNilaiService;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LaporanNilaiController extends Controller
{
    public function __construct(
        protected LaporanNilaiService $laporanNilai,
    ) {}

    /**
     * Laporan input nilai berdasarkan tahun akademik.
     *
     * Menampilkan progres input nilai per mata kuliah dan dosen pengajar
     * pada tahun akademik yang diminta.
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'tahun_akademik'      => ['required', 'string', 'regex:/^\d{4}[12]$/'],
            'kode_program_studi'  => ['nullable', 'string', 'max:20'],
        ], [
            'tahun_akademik.required' => 'Tahun akademik wajib diisi.',
            'tahun_akademik.regex'    => 'Format tahun akademik tidak valid. Gunakan format YYYY1 (Ganjil) atau YYYY2 (Genap), contoh: 20241.',
            'kode_program_studi.max'  => 'Kode program studi maksimal :max karakter.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(
                'Validasi gagal',
                $validator->errors(),
                422,
                ErrorCode::VALIDATION_FAILED,
            );
        }

        $tahunAkademik = $request->json('tahun_akademik');
        $kodeProdi     = $request->json('kode_program_studi');

        try {
            $laporan = $this->laporanNilai->getLaporan($tahunAkademik, $kodeProdi);
        } catch (QueryException $e) {
            return ApiResponse::error(
                'Gagal terhubung ke database. Silakan coba beberapa saat lagi.',
                null,
                503,
                ErrorCode::SERVICE_UNAVAILABLE,
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 404, ErrorCode::DATA_NOT_FOUND);
        } catch (\Throwable $e) {
            return ApiResponse::error('Gagal memproses permintaan', null, 500, ErrorCode::INTERNAL_ERROR);
        }

        return ApiResponse::success($laporan, 'Laporan input nilai berhasil diambil.');
    }
}
