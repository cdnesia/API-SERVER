<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TagihanService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TagihanController extends Controller
{
    public function __construct(
        protected TagihanService $tagihanService,
    ) {}

    /**
     * Daftar tagihan mahasiswa.
     *
     * Endpoint:  POST /api/tagihan
     * Body (JSON): { "npm": "...", "periode?": "..." }
     *
     * - npm     (required) NPM mahasiswa
     * - periode (optional) filter tahun akademik, format YYYY1/YYYYY2, contoh: "20231"
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'npm'     => ['required', 'string', 'max:20'],
            'periode' => ['nullable', 'string', 'regex:/^\d{4}[12]$/'],
        ], [
            'npm.required'  => 'NPM wajib diisi.',
            'npm.max'       => 'NPM maksimal :max karakter.',
            'periode.regex' => 'Format periode tidak valid. Gunakan format YYYY1 (Ganjil) atau YYYY2 (Genap), contoh: 20241.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }

        $npm     = $request->json('npm');
        $periode = $request->json('periode');

        try {
            $tagihan = $periode
                ? $this->tagihanService->getByNpmAndPeriode($npm, $periode)
                : $this->tagihanService->getByNpm($npm);

            return ApiResponse::success(
                $tagihan->map(fn ($t) => $this->tagihanService->formatTagihan($t))
            );
        } catch (\Throwable $e) {
            return ApiResponse::error('Gagal mengambil data tagihan', null, 500);
        }
    }

    /**
     * Ringkasan tagihan mahasiswa.
     *
     * Endpoint:  POST /api/tagihan/summary
     * Body (JSON): { "npm": "...", "periode?": "..." }
     */
    public function summary(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'npm'     => ['required', 'string', 'max:20'],
            'periode' => ['nullable', 'string', 'regex:/^\d{4}[12]$/'],
        ], [
            'npm.required'  => 'NPM wajib diisi.',
            'npm.max'       => 'NPM maksimal :max karakter.',
            'periode.regex' => 'Format periode tidak valid. Gunakan format YYYY1 (Ganjil) atau YYYY2 (Genap), contoh: 20241.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }

        $npm     = $request->json('npm');
        $periode = $request->json('periode');

        try {
            return ApiResponse::success($this->tagihanService->getSummary($npm, $periode));
        } catch (\Throwable $e) {
            return ApiResponse::error('Gagal mengambil ringkasan tagihan', null, 500);
        }
    }

    /**
     * Detail satu tagihan (termasuk pembayaran).
     *
     * Endpoint:  POST /api/tagihan/detail
     * Body (JSON): { "id_record_tagihan": "2025-00001" }
     */
    public function detail(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'id_record_tagihan' => ['required', 'string', 'max:50'],
        ], [
            'id_record_tagihan.required' => 'ID record tagihan wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }

        $idRecord = $request->json('id_record_tagihan');

        try {
            $tagihan = $this->tagihanService->getByIdRecord($idRecord);

            if (! $tagihan) {
                return ApiResponse::error('Tagihan tidak ditemukan.', null, 404);
            }

            return ApiResponse::success([
                'tagihan'    => $this->tagihanService->formatTagihan($tagihan),
                'pembayaran' => $tagihan->pembayaran->map(
                    fn ($p) => $this->tagihanService->formatPembayaran($p)
                ),
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Gagal mengambil detail tagihan', null, 500);
        }
    }

    /**
     * Cek status lunas tagihan.
     *
     * Endpoint:  POST /api/tagihan/cek-lunas
     * Body (JSON): { "npm": "...", "periode?": "..." }
     */
    public function cekLunas(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'npm'     => ['required', 'string', 'max:20'],
            'periode' => ['nullable', 'string', 'regex:/^\d{4}[12]$/'],
        ], [
            'npm.required'  => 'NPM wajib diisi.',
            'npm.max'       => 'NPM maksimal :max karakter.',
            'periode.regex' => 'Format periode tidak valid. Gunakan format YYYY1 (Ganjil) atau YYYY2 (Genap), contoh: 20241.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }

        $npm     = $request->json('npm');
        $periode = $request->json('periode');

        try {
            $tagihan = $periode
                ? $this->tagihanService->getByNpmAndPeriode($npm, $periode)
                : $this->tagihanService->getAktifByNpm($npm);

            if ($tagihan->isEmpty()) {
                return ApiResponse::success([
                    'lunas'   => true,
                    'message' => 'Tidak ada tagihan.',
                ]);
            }

            $semuaLunas = $tagihan->every(fn ($t) => $this->tagihanService->isLunas($t));

            return ApiResponse::success([
                'lunas'       => $semuaLunas,
                'total'       => $tagihan->count(),
                'rincian'     => $tagihan->map(fn ($t) => [
                    'nomor_tagihan'  => $t->nomor_tagihan,
                    'jenis_tagihan'  => $t->jenis_tagihan,
                    'tahun_akademik' => $t->tahun_akademik,
                    'ditagih'        => (float) $t->nominal_ditagih,
                    'terbayar'       => (float) $t->nominal_terbayar,
                    'lunas'          => $this->tagihanService->isLunas($t),
                ]),
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Gagal mengecek status tagihan', null, 500);
        }
    }
}
