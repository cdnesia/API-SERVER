<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mahasiswa;
use App\Services\TagihanService;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TagihanController extends Controller
{
    public function __construct(
        protected TagihanService $tagihanService,
    ) {}

    /**
     * Ambil daftar tagihan berdasarkan NPM, opsional filter periode.
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $npmInput     = $request->json('npms');
        $periodeInput = $request->json('tahun_akademik');

        $rules = [
            'npm'     => is_array($npmInput) ? ['required', 'array', 'min:1'] : ['required', 'string', 'max:20'],
            'periode' => is_array($periodeInput) ? ['nullable', 'array'] : ['nullable', 'string', 'regex:/^\d{4}[12]$/'],
        ];

        if (is_array($npmInput)) {
            $rules['npm.*'] = ['string', 'max:20'];
        }

        if (is_array($periodeInput)) {
            $rules['periode.*'] = ['string', 'regex:/^\d{4}[12]$/'];
        }

        $validator = Validator::make($request->json()->all(), $rules, [
            'npm.required'     => 'NPM wajib diisi.',
            'npm.array'        => 'NPM harus berupa string atau array NPM.',
            'npm.max'          => 'NPM maksimal :max karakter.',
            'npm.*.max'        => 'NPM maksimal :max karakter.',
            'periode.regex'    => 'Format periode tidak valid. Gunakan format YYYY1 (Ganjil) atau YYYY2 (Genap), contoh: 20241.',
            'periode.*.regex'  => 'Format periode tidak valid. Gunakan format YYYY1 (Ganjil) atau YYYY2 (Genap), contoh: 20241.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $npm     = $npmInput;
        $periode = $periodeInput;

        try {
            if (is_array($npm)) {
                $periodeList = $periode ? (array) $periode : [];
                $tagihan     = $this->tagihanService->getByNpms($npm, $periodeList);
            } else {
                $tagihan = $periode
                    ? $this->tagihanService->getByNpmAndPeriode($npm, $periode)
                    : $this->tagihanService->getByNpm($npm);
            }

            return ApiResponse::success(
                $tagihan->map(fn ($t) => $this->tagihanService->formatTagihan($t))
            );
        } catch (\Throwable $e) {
            Log::error('TagihanController: gagal mengambil data tagihan.', ['npm' => $npm, 'message' => $e->getMessage()]);

            return ApiResponse::error('Gagal mengambil data tagihan', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Buat tagihan baru (general-purpose, semua jenis tagihan).
     *
     * Data mahasiswa (nama, prodi, fakultas) diambil otomatis dari NPM —
     * cukup kirim npm, tahun_akademik, dan total_tagihan.
     *
     * Body: { "npm": "...", "tahun_akademik": "20241", "total_tagihan": 5000000,
     *         "jenis_tagihan": "...", "id_kelas_perkuliahan": "...", "nama_kelas_perkuliahan": "...",
     *         "waktu_berakhir": "2025-12-31", "nominal_ditagih": 5000000,
     *         "detail_tagihan": [...], "total_potongan": 0, "detail_potongan": [...], "status_aktif": "Y" }
     */
    public function create(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'npm'                    => ['required', 'string', 'max:20'],
            'tahun_akademik'         => ['required', 'string', 'regex:/^\d{4}[12]$/'],
            'total_tagihan'          => ['required', 'numeric', 'min:0'],
            'id_kelas_perkuliahan'   => ['nullable', 'string', 'max:50'],
            'nama_kelas_perkuliahan' => ['nullable', 'string', 'max:100'],
            'waktu_berakhir'         => ['nullable', 'date'],
            'jenis_tagihan'          => ['nullable', 'string', 'max:50'],
            'status_aktif'           => ['nullable', 'string', 'in:Y,T'],
            'nominal_ditagih'        => ['nullable', 'numeric', 'min:0'],
            'total_potongan'         => ['nullable', 'numeric', 'min:0'],
            'detail_tagihan'         => ['nullable', 'array'],
            'detail_potongan'        => ['nullable', 'array'],
            'khs'                    => ['nullable', 'integer'],
        ], [
            'npm.required'            => 'NPM wajib diisi.',
            'tahun_akademik.required' => 'Tahun akademik wajib diisi.',
            'tahun_akademik.regex'    => 'Format tahun akademik tidak valid (YYYY1/YYYY2).',
            'status_aktif.in'         => 'Status aktif harus Y atau T.',
            'total_tagihan.required'  => 'Total tagihan wajib diisi.',
            'total_tagihan.numeric'   => 'Total tagihan harus berupa angka.',
            'total_tagihan.min'       => 'Total tagihan minimal 0.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $npm = $request->json('npm');

        $mahasiswa = Mahasiswa::with('programStudi.fakultas')
            ->where('npm', $npm)
            ->first();

        if (! $mahasiswa) {
            return ApiResponse::error("Mahasiswa dengan NPM {$npm} tidak ditemukan.", null, 404, ErrorCode::DATA_NOT_FOUND);
        }

        $data = array_merge($request->json()->all(), [
            'nama_mahasiswa'     => $mahasiswa->nama_mahasiswa,
            'kode_program_studi' => $mahasiswa->kode_program_studi,
            'nama_program_studi' => $mahasiswa->programStudi?->nama_program_studi_idn,
            'nama_fakultas'      => $mahasiswa->programStudi?->fakultas?->nama_fakultas_idn,
        ]);

        try {
            $tagihan = $this->tagihanService->create($data);

            return ApiResponse::success([
                'message'        => 'Tagihan berhasil dibuat.',
                'nomor_tagihan'  => $tagihan->nomor_tagihan,
                'data'           => $this->tagihanService->formatTagihan($tagihan),
            ]);
        } catch (\Throwable $e) {
            Log::error('TagihanController: gagal membuat tagihan.', ['npm' => $npm, 'message' => $e->getMessage()]);

            return ApiResponse::error('Gagal membuat tagihan', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Update tagihan berdasarkan nomor_tagihan.
     *
     * Body: { "nomor_tagihan": "...", "npm": "...", ... }
     */
    public function update(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'nomor_tagihan' => ['required', 'string', 'max:50'],
        ], [
            'nomor_tagihan.required' => 'Nomor tagihan wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $editableFields = [
            'npm',
            'nama_mahasiswa',
            'kode_prodi',
            'id_kelas_perkuliahan',
            'tahun_akademik',
            'jumlah_tagihan',
            'status_aktif',
            'waktu_berakhir',
            'jenis_tagihan',
            'nominal_ditagih',
        ];

        $updateData = [];
        foreach ($editableFields as $field) {
            if ($request->json()->has($field)) {
                $updateData[$field] = $request->json($field);
            }
        }

        if (empty($updateData)) {
            return ApiResponse::error('Minimal satu field harus diisi untuk update.', null, 400);
        }

        try {
            $tagihan = $this->tagihanService->updateByNomor(
                $request->json('nomor_tagihan'),
                $updateData
            );

            if (! $tagihan) {
                return ApiResponse::error('Tagihan tidak ditemukan.', null, 404, ErrorCode::DATA_NOT_FOUND);
            }

            return ApiResponse::success([
                'message' => 'Tagihan berhasil diperbarui.',
                'data'    => $this->tagihanService->formatTagihan($tagihan),
            ]);
        } catch (\Throwable $e) {
            Log::error('TagihanController: gagal memperbarui tagihan.', ['nomor_tagihan' => $request->json('nomor_tagihan'), 'message' => $e->getMessage()]);

            return ApiResponse::error('Gagal memperbarui tagihan', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Hapus tagihan berdasarkan nomor_tagihan (soft delete).
     *
     * Body: { "nomor_tagihan": "..." }
     */
    public function delete(Request $request): \Illuminate\Http\JsonResponse
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
            $deleted = $this->tagihanService->deleteByNomor($nomorTagihan);

            if (! $deleted) {
                return ApiResponse::error('Tagihan tidak ditemukan.', null, 404, ErrorCode::DATA_NOT_FOUND);
            }

            return ApiResponse::success(['message' => 'Tagihan berhasil dihapus.']);
        } catch (\Throwable $e) {
            Log::error('TagihanController: gagal menghapus tagihan.', ['nomor_tagihan' => $nomorTagihan, 'message' => $e->getMessage()]);

            return ApiResponse::error('Gagal menghapus tagihan', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }
}
