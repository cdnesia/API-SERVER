<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BipotService;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BipotController extends Controller
{
    public function __construct(
        protected BipotService $bipotService,
    ) {}

    // ──────────────────────────────────────────────
    //  Master Bipot (jenis biaya)
    // ──────────────────────────────────────────────

    /**
     * Ambil daftar seluruh jenis biaya (bipot).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $bipot = $this->bipotService->getAllBipot();

            return ApiResponse::success(
                $bipot->map(fn ($b) => $this->bipotService->formatBipot($b))
            );
        } catch (\Throwable $e) {
            Log::error('BipotController: gagal mengambil data master bipot.', ['message' => $e->getMessage()]);

            return ApiResponse::error('Gagal mengambil data bipot', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Buat jenis biaya (bipot) baru.
     *
     * Body: { "nama_bipot": "...", "trxid": 1, "urutan": 10 }
     */
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'nama_bipot' => ['required', 'string', 'max:255'],
            'trxid'      => ['required', 'integer'],
            'urutan'     => ['nullable', 'integer'],
        ], [
            'nama_bipot.required' => 'Nama bipot wajib diisi.',
            'trxid.required'      => 'Trxid wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        try {
            $bipot = $this->bipotService->createBipot($request->json()->all());

            return ApiResponse::success([
                'message' => 'Bipot berhasil dibuat.',
                'data'    => $this->bipotService->formatBipot($bipot),
            ]);
        } catch (\Throwable $e) {
            Log::error('BipotController: gagal membuat bipot.', ['message' => $e->getMessage()]);

            return ApiResponse::error('Gagal membuat bipot', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Update jenis biaya (bipot) berdasarkan id.
     *
     * Body: { "id": 1, "nama_bipot": "...", "trxid": 1, "urutan": 10 }
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'id'         => ['required', 'integer'],
            'nama_bipot' => ['nullable', 'string', 'max:255'],
            'trxid'      => ['nullable', 'integer'],
            'urutan'     => ['nullable', 'integer'],
        ], [
            'id.required' => 'Id bipot wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $updateData = $request->json()->except('id');

        if (empty($updateData)) {
            return ApiResponse::error('Minimal satu field harus diisi untuk update.', null, 400);
        }

        try {
            $bipot = $this->bipotService->updateBipot((int) $request->json('id'), $updateData);

            if (! $bipot) {
                return ApiResponse::error('Bipot tidak ditemukan.', null, 404, ErrorCode::DATA_NOT_FOUND);
            }

            return ApiResponse::success([
                'message' => 'Bipot berhasil diperbarui.',
                'data'    => $this->bipotService->formatBipot($bipot),
            ]);
        } catch (\Throwable $e) {
            Log::error('BipotController: gagal memperbarui bipot.', ['id' => $request->json('id'), 'message' => $e->getMessage()]);

            return ApiResponse::error('Gagal memperbarui bipot', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Hapus jenis biaya (bipot) berdasarkan id.
     *
     * Body: { "id": 1 }
     */
    public function delete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'id' => ['required', 'integer'],
        ], [
            'id.required' => 'Id bipot wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $id = (int) $request->json('id');

        try {
            $deleted = $this->bipotService->deleteBipot($id);

            if (! $deleted) {
                return ApiResponse::error('Bipot tidak ditemukan.', null, 404, ErrorCode::DATA_NOT_FOUND);
            }

            return ApiResponse::success(['message' => 'Bipot berhasil dihapus.']);
        } catch (\Throwable $e) {
            Log::error('BipotController: gagal menghapus bipot.', ['id' => $id, 'message' => $e->getMessage()]);

            return ApiResponse::error('Gagal menghapus bipot', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    // ──────────────────────────────────────────────
    //  Bipot per Angkatan
    // ──────────────────────────────────────────────

    /**
     * Ambil mapping bipot per angkatan, opsional filter kode_prodi / kode_tahun / id_program_kuliah.
     *
     * Body: { "kode_prodi": "55201", "kode_tahun": "20241", "id_program_kuliah": 1 }
     */
    public function angkatan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'kode_prodi'        => ['nullable', 'string', 'max:10'],
            'kode_tahun'        => ['nullable', 'string', 'max:6'],
            'id_program_kuliah' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        try {
            $angkatan = $this->bipotService->getAngkatan(
                $request->json('kode_prodi'),
                $request->json('kode_tahun'),
                $request->json('id_program_kuliah') !== null ? (int) $request->json('id_program_kuliah') : null,
            );

            return ApiResponse::success(
                $angkatan->map(fn ($a) => $this->bipotService->formatAngkatan($a))
            );
        } catch (\Throwable $e) {
            Log::error('BipotController: gagal mengambil data bipot per angkatan.', ['message' => $e->getMessage()]);

            return ApiResponse::error('Gagal mengambil data bipot per angkatan', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Buat mapping bipot per angkatan baru.
     *
     * Body: { "kode_tahun": "20241", "nama_tahun": "2024/2025", "id_program_kuliah": 1, "kode_prodi": "55201" }
     */
    public function angkatanCreate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'kode_tahun'        => ['required', 'string', 'max:6'],
            'nama_tahun'        => ['required', 'string', 'max:250'],
            'id_program_kuliah' => ['required', 'integer'],
            'kode_prodi'        => ['required', 'string', 'max:10'],
        ], [
            'kode_tahun.required'        => 'Kode tahun wajib diisi.',
            'nama_tahun.required'        => 'Nama tahun wajib diisi.',
            'id_program_kuliah.required' => 'Id program kuliah wajib diisi.',
            'kode_prodi.required'        => 'Kode prodi wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        try {
            $angkatan = $this->bipotService->createAngkatan($request->json()->all());

            return ApiResponse::success([
                'message' => 'Bipot per angkatan berhasil dibuat.',
                'data'    => $this->bipotService->formatAngkatan($angkatan),
            ]);
        } catch (\Throwable $e) {
            Log::error('BipotController: gagal membuat bipot per angkatan.', ['message' => $e->getMessage()]);

            return ApiResponse::error('Gagal membuat bipot per angkatan', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Update mapping bipot per angkatan berdasarkan id.
     *
     * Body: { "id": 1, "kode_tahun": "...", "nama_tahun": "...", "id_program_kuliah": 1, "kode_prodi": "..." }
     */
    public function angkatanUpdate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'id' => ['required', 'integer'],
        ], [
            'id.required' => 'Id wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $updateData = $request->json()->except('id');

        if (empty($updateData)) {
            return ApiResponse::error('Minimal satu field harus diisi untuk update.', null, 400);
        }

        try {
            $angkatan = $this->bipotService->updateAngkatan((int) $request->json('id'), $updateData);

            if (! $angkatan) {
                return ApiResponse::error('Bipot per angkatan tidak ditemukan.', null, 404, ErrorCode::DATA_NOT_FOUND);
            }

            return ApiResponse::success([
                'message' => 'Bipot per angkatan berhasil diperbarui.',
                'data'    => $this->bipotService->formatAngkatan($angkatan),
            ]);
        } catch (\Throwable $e) {
            Log::error('BipotController: gagal memperbarui bipot per angkatan.', ['id' => $request->json('id'), 'message' => $e->getMessage()]);

            return ApiResponse::error('Gagal memperbarui bipot per angkatan', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Hapus mapping bipot per angkatan berdasarkan id.
     *
     * Body: { "id": 1 }
     */
    public function angkatanDelete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'id' => ['required', 'integer'],
        ], [
            'id.required' => 'Id wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $id = (int) $request->json('id');

        try {
            $deleted = $this->bipotService->deleteAngkatan($id);

            if (! $deleted) {
                return ApiResponse::error('Bipot per angkatan tidak ditemukan.', null, 404, ErrorCode::DATA_NOT_FOUND);
            }

            return ApiResponse::success(['message' => 'Bipot per angkatan berhasil dihapus.']);
        } catch (\Throwable $e) {
            Log::error('BipotController: gagal menghapus bipot per angkatan.', ['id' => $id, 'message' => $e->getMessage()]);

            return ApiResponse::error('Gagal menghapus bipot per angkatan', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    // ──────────────────────────────────────────────
    //  Rincian Biaya (nominal per semester)
    // ──────────────────────────────────────────────

    /**
     * Ambil rincian biaya (nominal per jenis bipot) untuk satu prodi + tahun akademik.
     *
     * Body: { "kode_prodi": "55201", "kode_tahun": "20241", "semester": 1, "status_mahasiswa": 1 }
     */
    public function rincian(Request $request): JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'kode_prodi'       => ['required', 'string', 'max:10'],
            'kode_tahun'       => ['required', 'string', 'max:6'],
            'semester'         => ['nullable', 'integer'],
            'status_mahasiswa' => ['nullable', 'integer'],
        ], [
            'kode_prodi.required' => 'Kode prodi wajib diisi.',
            'kode_tahun.required' => 'Kode tahun wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        try {
            $rincian = $this->bipotService->getRincianBiaya(
                $request->json('kode_prodi'),
                $request->json('kode_tahun'),
                $request->json('semester') !== null ? (int) $request->json('semester') : null,
                $request->json('status_mahasiswa') !== null ? (int) $request->json('status_mahasiswa') : null,
            );

            $items = $rincian->map(fn ($item) => $this->bipotService->formatPerSemester($item));

            return ApiResponse::success([
                'total_nominal' => $items->sum('nominal'),
                'jumlah_item'   => $items->count(),
                'rincian'       => $items,
            ]);
        } catch (\Throwable $e) {
            Log::error('BipotController: gagal mengambil rincian biaya bipot.', [
                'kode_prodi' => $request->json('kode_prodi'),
                'kode_tahun' => $request->json('kode_tahun'),
                'message'    => $e->getMessage(),
            ]);

            return ApiResponse::error('Gagal mengambil rincian biaya bipot', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Buat rincian nominal bipot per semester baru.
     *
     * Body: { "id_bipot_angkatan": 1, "id_bipot": 1, "nominal": 500000, "semester": 1,
     *         "status_awal": [1,3], "status_mahasiswa": [1] }
     */
    public function rincianCreate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'id_bipot_angkatan' => ['required', 'integer'],
            'id_bipot'          => ['required', 'integer'],
            'nominal'           => ['required', 'numeric', 'min:0'],
            'semester'          => ['nullable', 'integer'],
            'status_awal'       => ['nullable', 'array'],
            'status_mahasiswa'  => ['nullable', 'array'],
        ], [
            'id_bipot_angkatan.required' => 'Id bipot angkatan wajib diisi.',
            'id_bipot.required'          => 'Id bipot wajib diisi.',
            'nominal.required'           => 'Nominal wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        try {
            $item = $this->bipotService->createPerSemester($request->json()->all());

            return ApiResponse::success([
                'message' => 'Rincian biaya bipot berhasil dibuat.',
                'data'    => $this->bipotService->formatPerSemester($item->fresh(['bipot', 'angkatan'])),
            ]);
        } catch (\Throwable $e) {
            Log::error('BipotController: gagal membuat rincian biaya bipot.', ['message' => $e->getMessage()]);

            return ApiResponse::error('Gagal membuat rincian biaya bipot', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Update rincian nominal bipot per semester berdasarkan id.
     *
     * Body: { "id": 1, "nominal": 500000, "semester": 1, "status_awal": [1,3], "status_mahasiswa": [1] }
     */
    public function rincianUpdate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'id' => ['required', 'integer'],
        ], [
            'id.required' => 'Id wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $updateData = $request->json()->except('id');

        if (empty($updateData)) {
            return ApiResponse::error('Minimal satu field harus diisi untuk update.', null, 400);
        }

        try {
            $item = $this->bipotService->updatePerSemester((int) $request->json('id'), $updateData);

            if (! $item) {
                return ApiResponse::error('Rincian biaya bipot tidak ditemukan.', null, 404, ErrorCode::DATA_NOT_FOUND);
            }

            return ApiResponse::success([
                'message' => 'Rincian biaya bipot berhasil diperbarui.',
                'data'    => $this->bipotService->formatPerSemester($item->fresh(['bipot', 'angkatan'])),
            ]);
        } catch (\Throwable $e) {
            Log::error('BipotController: gagal memperbarui rincian biaya bipot.', ['id' => $request->json('id'), 'message' => $e->getMessage()]);

            return ApiResponse::error('Gagal memperbarui rincian biaya bipot', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Hapus rincian nominal bipot per semester berdasarkan id.
     *
     * Body: { "id": 1 }
     */
    public function rincianDelete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'id' => ['required', 'integer'],
        ], [
            'id.required' => 'Id wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $id = (int) $request->json('id');

        try {
            $deleted = $this->bipotService->deletePerSemester($id);

            if (! $deleted) {
                return ApiResponse::error('Rincian biaya bipot tidak ditemukan.', null, 404, ErrorCode::DATA_NOT_FOUND);
            }

            return ApiResponse::success(['message' => 'Rincian biaya bipot berhasil dihapus.']);
        } catch (\Throwable $e) {
            Log::error('BipotController: gagal menghapus rincian biaya bipot.', ['id' => $id, 'message' => $e->getMessage()]);

            return ApiResponse::error('Gagal menghapus rincian biaya bipot', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }
}
