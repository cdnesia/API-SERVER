<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PembayaranService;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PembayaranController extends Controller
{
    public function __construct(
        protected PembayaranService $pembayaranService,
    ) {}

    /**
     * Ambil daftar pembayaran berdasarkan NPM.
     *
     * Body: { "npm": "...", "periode": "20241" }
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
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $npm = $request->json('npm');

        try {
            $pembayaran = $this->pembayaranService->getByNpm($npm);

            return ApiResponse::success(
                $pembayaran->map(fn ($p) => $this->pembayaranService->formatPembayaran($p))
            );
        } catch (\Throwable $e) {
            Log::error('PembayaranController: gagal mengambil data pembayaran.', ['npm' => $npm, 'message' => $e->getMessage()]);

            return ApiResponse::error('Gagal mengambil data pembayaran', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Ambil detail satu pembayaran berdasarkan id_record_pembayaran.
     *
     * Body: { "id_record_pembayaran": "..." }
     */
    public function detail(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'id_record_pembayaran' => ['required', 'string', 'max:50'],
        ], [
            'id_record_pembayaran.required' => 'ID record pembayaran wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $idRecord = $request->json('id_record_pembayaran');

        try {
            $pembayaran = $this->pembayaranService->getByIdRecord($idRecord);

            if (! $pembayaran) {
                return ApiResponse::error('Pembayaran tidak ditemukan.', null, 404, ErrorCode::DATA_NOT_FOUND);
            }

            return ApiResponse::success(
                $this->pembayaranService->formatPembayaran($pembayaran)
            );
        } catch (\Throwable $e) {
            Log::error('PembayaranController: gagal mengambil detail pembayaran.', ['id_record_pembayaran' => $idRecord, 'message' => $e->getMessage()]);

            return ApiResponse::error('Gagal mengambil detail pembayaran', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Ambil pembayaran berdasarkan id_record_tagihan.
     *
     * Body: { "id_record_tagihan": "..." }
     */
    public function byTagihan(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'id_record_tagihan' => ['required', 'string', 'max:50'],
        ], [
            'id_record_tagihan.required' => 'ID record tagihan wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $idRecordTagihan = $request->json('id_record_tagihan');

        try {
            $pembayaran = $this->pembayaranService->getByIdRecordTagihan($idRecordTagihan);

            return ApiResponse::success(
                $pembayaran->map(fn ($p) => $this->pembayaranService->formatPembayaran($p))
            );
        } catch (\Throwable $e) {
            Log::error('PembayaranController: gagal mengambil pembayaran by tagihan.', ['id_record_tagihan' => $idRecordTagihan, 'message' => $e->getMessage()]);

            return ApiResponse::error('Gagal mengambil data pembayaran', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Ambil pembayaran berdasarkan nomor_tagihan.
     *
     * Body: { "nomor_tagihan": "..." }
     */
    public function byNomorTagihan(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'nomor_tagihan' => ['required', 'string', 'max:50'],
        ], [
            'nomor_tagihan.required' => 'Nomor tagihan wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $nomorTagihan = $request->json('nomor_tagihan');

        try {
            $pembayaran = $this->pembayaranService->getByNomorTagihan($nomorTagihan);

            return ApiResponse::success(
                $pembayaran->map(fn ($p) => $this->pembayaranService->formatPembayaran($p))
            );
        } catch (\Throwable $e) {
            Log::error('PembayaranController: gagal mengambil pembayaran by nomor tagihan.', ['nomor_tagihan' => $nomorTagihan, 'message' => $e->getMessage()]);

            return ApiResponse::error('Gagal mengambil data pembayaran', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Ringkasan pembayaran per NPM.
     *
     * Body: { "npm": "..." }
     */
    public function summary(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'npm' => ['required', 'string', 'max:20'],
        ], [
            'npm.required' => 'NPM wajib diisi.',
            'npm.max'      => 'NPM maksimal :max karakter.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $npm = $request->json('npm');

        try {
            return ApiResponse::success(
                $this->pembayaranService->getSummaryByNpm($npm)
            );
        } catch (\Throwable $e) {
            Log::error('PembayaranController: gagal mengambil ringkasan pembayaran.', ['npm' => $npm, 'message' => $e->getMessage()]);

            return ApiResponse::error('Gagal mengambil ringkasan pembayaran', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Ambil pembayaran dalam rentang tanggal.
     *
     * Body: { "start_date": "2024-01-01", "end_date": "2024-12-31" }
     */
    public function byDateRange(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date'   => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ], [
            'start_date.required'        => 'Tanggal mulai wajib diisi.',
            'start_date.date_format'     => 'Format tanggal mulai tidak valid (YYYY-MM-DD).',
            'end_date.required'          => 'Tanggal akhir wajib diisi.',
            'end_date.date_format'       => 'Format tanggal akhir tidak valid (YYYY-MM-DD).',
            'end_date.after_or_equal'    => 'Tanggal akhir harus setelah atau sama dengan tanggal mulai.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $startDate = $request->json('start_date');
        $endDate   = $request->json('end_date');

        try {
            $pembayaran = $this->pembayaranService->getByDateRange($startDate, $endDate);

            return ApiResponse::success(
                $pembayaran->map(fn ($p) => $this->pembayaranService->formatPembayaran($p))
            );
        } catch (\Throwable $e) {
            Log::error('PembayaranController: gagal mengambil pembayaran by date range.', ['start_date' => $startDate, 'end_date' => $endDate, 'message' => $e->getMessage()]);

            return ApiResponse::error('Gagal mengambil data pembayaran', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }
}
