<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TagihanService;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TagihanController extends Controller
{
    public function __construct(
        protected TagihanService $tagihanService,
    ) {}

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
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
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
            return ApiResponse::error('Gagal mengambil data tagihan', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

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
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $npm     = $request->json('npm');
        $periode = $request->json('periode');

        try {
            return ApiResponse::success($this->tagihanService->getSummary($npm, $periode));
        } catch (\Throwable $e) {
            return ApiResponse::error('Gagal mengambil ringkasan tagihan', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    public function detail(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'id_record_tagihan' => ['required', 'string', 'max:50'],
        ], [
            'id_record_tagihan.required' => 'ID record tagihan wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $idRecord = $request->json('id_record_tagihan');

        try {
            $tagihan = $this->tagihanService->getByIdRecord($idRecord);

            if (! $tagihan) {
                return ApiResponse::error('Tagihan tidak ditemukan.', null, 404, ErrorCode::DATA_NOT_FOUND);
            }

            return ApiResponse::success([
                'tagihan'    => $this->tagihanService->formatTagihan($tagihan),
                'pembayaran' => $tagihan->pembayaran->map(
                    fn ($p) => $this->tagihanService->formatPembayaran($p)
                ),
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Gagal mengambil detail tagihan', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

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
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
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
            return ApiResponse::error('Gagal mengecek status tagihan', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }
}
