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

    /**
     * Cek tagihan untuk banyak NPM sekaligus.
     *
     * Body: { "npms": ["...", "..."], "tahun_akademik": ["20241", "20242"] }
     */
    public function massal(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'npms'            => ['required', 'array', 'min:1'],
            'npms.*'          => ['required', 'string', 'max:20'],
            'tahun_akademik'  => ['nullable', 'array'],
            'tahun_akademik.*'=> ['string', 'regex:/^\d{4}[12]$/'],
        ], [
            'npms.required'         => 'npms wajib diisi.',
            'npms.array'            => 'npms harus berupa array.',
            'npms.min'              => 'Minimal 1 NPM.',
            'npms.*.required'       => 'NPM wajib diisi.',
            'tahun_akademik.*.regex'=> 'Format tahun akademik tidak valid (YYYY1/YYYY2).',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $npms           = $request->json('npms');
        $tahunAkademik  = $request->json('tahun_akademik', []);

        try {
            $data = $this->tagihanService->getByNpms($npms, $tahunAkademik);

            $grouped = [];
            foreach ($data as $row) {
                $grouped[$row->npm][] = $this->tagihanService->formatTagihan($row);
            }

            return ApiResponse::success([
                'jumlah_npm'     => count($npms),
                'npm_ditemukan'  => count($grouped),
                'jumlah_data'    => $data->count(),
                'data'           => $grouped,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Gagal mengambil data tagihan', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Edit tagihan berdasarkan nomor_tagihan.
     *
     * Body: { "nomor_tagihan": "...", "npm": "...", ... }
     */
    public function edit(Request $request): \Illuminate\Http\JsonResponse
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
            return ApiResponse::error('Gagal memperbarui tagihan', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Buat tagihan PMB (Penerimaan Mahasiswa Baru).
     *
     * Body: { "npm": "...", "tahun_akademik": "20241", "nama_mahasiswa": "...",
     *         "kode_prodi": "...", "jumlah_tagihan": 5000000, "id_kelas_perkuliahan": "..." }
     */
    public function createPMB(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'npm'                  => ['required', 'string', 'max:20'],
            'tahun_akademik'       => ['required', 'string', 'regex:/^\d{4}[12]$/'],
            'nama_mahasiswa'       => ['required', 'string', 'max:100'],
            'kode_prodi'           => ['required', 'string', 'max:20'],
            'id_kelas_perkuliahan' => ['required', 'string', 'max:50'],
            'jumlah_tagihan'       => ['required', 'numeric', 'min:0'],
        ], [
            'npm.required'                  => 'NPM wajib diisi.',
            'tahun_akademik.required'       => 'Tahun akademik wajib diisi.',
            'tahun_akademik.regex'          => 'Format tahun akademik tidak valid (YYYY1/YYYY2).',
            'nama_mahasiswa.required'       => 'Nama mahasiswa wajib diisi.',
            'kode_prodi.required'           => 'Kode prodi wajib diisi.',
            'id_kelas_perkuliahan.required' => 'ID kelas perkuliahan wajib diisi.',
            'jumlah_tagihan.required'       => 'Jumlah tagihan wajib diisi.',
            'jumlah_tagihan.numeric'        => 'Jumlah tagihan harus berupa angka.',
            'jumlah_tagihan.min'            => 'Jumlah tagihan minimal 0.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        try {
            $tagihan = $this->tagihanService->createPMB($request->json()->all());

            return ApiResponse::success([
                'message'        => 'Tagihan berhasil dibuat.',
                'nomor_tagihan'  => $tagihan->nomor_tagihan,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Gagal membuat tagihan', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }
}
