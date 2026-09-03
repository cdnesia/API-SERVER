<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tagihan;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TagihanPmbController extends Controller
{
    public function get(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'npm'     => ['required', 'string', 'max:30'],
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
            $query = Tagihan::where('npm', $npm);

            if ($periode) {
                $query->where('tahun_akademik', $periode)->orderBy('created_at', 'desc');
            } else {
                $query->orderBy('tahun_akademik', 'desc');
            }

            $tagihan = $query->get();

            return ApiResponse::success(
                $tagihan->map(fn ($t) => $this->formatTagihan($t))
            );
        } catch (\Throwable $e) {
            Log::error('TagihanController: gagal mengambil data tagihan.', ['npm' => $npm, 'message' => $e->getMessage()]);

            return ApiResponse::error('Gagal mengambil data tagihan', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    public function cekLunas(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'npm'     => ['required', 'string', 'max:30'],
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
            if ($periode) {
                $tagihan = Tagihan::where('npm', $npm)
                    ->where('tahun_akademik', $periode)
                    ->orderBy('created_at', 'desc')
                    ->get();
            } else {
                $tagihan = Tagihan::where('npm', $npm)
                    ->where('status_aktif', 'Y')
                    ->orderBy('waktu_berakhir', 'asc')
                    ->get();
            }

            if ($tagihan->isEmpty()) {
                return ApiResponse::success([
                    'lunas'   => true,
                    'message' => 'Tidak ada tagihan.',
                ]);
            }

            $semuaLunas = $tagihan->every(fn ($t) => $this->isLunas($t));

            return ApiResponse::success([
                'lunas'   => $semuaLunas,
                'total'   => $tagihan->count(),
                'rincian' => $tagihan->map(fn ($t) => [
                    'nomor_tagihan'  => $t->nomor_tagihan,
                    'jenis_tagihan'  => $t->jenis_tagihan,
                    'tahun_akademik' => $t->tahun_akademik,
                    'ditagih'        => (float) $t->nominal_ditagih,
                    'terbayar'       => (float) $t->nominal_terbayar,
                    'lunas'          => $this->isLunas($t),
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::error('TagihanController: gagal mengecek status tagihan.', ['npm' => $npm, 'message' => $e->getMessage()]);

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
            'npms'             => ['required', 'array', 'min:1'],
            'npms.*'           => ['required', 'string', 'max:30'],
            'tahun_akademik'   => ['nullable', 'array'],
        ], [
            'npms.required'          => 'npms wajib diisi.',
            'npms.array'             => 'npms harus berupa array.',
            'npms.min'               => 'Minimal 1 NPM.',
            'npms.*.required'        => 'NPM wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $npms          = $request->json('npms');
        $tahunAkademik = $request->json('tahun_akademik', []);

        try {
            $query = Tagihan::whereIn('npm', $npms);

            if (! empty($tahunAkademik)) {
                $query->whereIn('tahun_akademik', $tahunAkademik);
            }

            $data = $query->orderBy('npm')->orderBy('tahun_akademik', 'desc')->get();

            $grouped = [];
            foreach ($data as $row) {
                $grouped[$row->npm][] = $this->formatTagihan($row);
            }

            return ApiResponse::success([
                'jumlah_npm'    => count($npms),
                'npm_ditemukan' => count($grouped),
                'jumlah_data'   => $data->count(),
                'data'          => $grouped,
            ]);
        } catch (\Throwable $e) {
            Log::error('TagihanController: gagal mengambil data tagihan massal.', ['npms' => $npms, 'message' => $e->getMessage()]);

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
            'nomor_tagihan' => ['required', 'string', 'max:30'],
        ], [
            'nomor_tagihan.required' => 'Nomor tagihan wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        // Mapping field dari request ke kolom tabel tagihan.
        $fieldMap = [
            'npm'                    => 'npm',
            'nama_mahasiswa'         => 'nama_mahasiswa',
            'nama_fakultas'          => 'nama_fakultas',
            'kode_prodi'             => 'kode_program_studi',
            'nama_program_studi'     => 'nama_program_studi',
            'id_kelas_perkuliahan'   => 'id_kelas_perkuliahan',
            'nama_kelas_perkuliahan' => 'nama_kelas_perkuliahan',
            'tahun_akademik'         => 'tahun_akademik',
            'waktu_berakhir'         => 'waktu_berakhir',
            'jenis_tagihan'          => 'jenis_tagihan',
            'jumlah_tagihan'         => 'total_tagihan',
            'nominal_ditagih'        => 'nominal_ditagih',
            'nominal_terbayar'       => 'nominal_terbayar',
            'status_aktif'           => 'status_aktif',
            'khs'                    => 'khs',
        ];

        $updateData = [];
        foreach ($fieldMap as $field => $column) {
            if ($request->json()->has($field)) {
                $updateData[$column] = $request->json($field);
            }
        }

        if (empty($updateData)) {
            return ApiResponse::error('Minimal satu field harus diisi untuk update.', null, 400);
        }

        $nomorTagihan = $request->json('nomor_tagihan');

        try {
            $tagihan = Tagihan::where('nomor_tagihan', $nomorTagihan)->first();

            if (! $tagihan) {
                return ApiResponse::error('Tagihan tidak ditemukan.', null, 404, ErrorCode::DATA_NOT_FOUND);
            }

            $tagihan->fill($updateData)->save();

            return ApiResponse::success([
                'message' => 'Tagihan berhasil diperbarui.',
                'data'    => $this->formatTagihan($tagihan->fresh()),
            ]);
        } catch (\Throwable $e) {
            Log::error('TagihanController: gagal memperbarui tagihan.', ['nomor_tagihan' => $nomorTagihan, 'message' => $e->getMessage()]);

            return ApiResponse::error('Gagal memperbarui tagihan', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Hapus tagihan berdasarkan nomor_tagihan (soft delete).
     *
     * Tagihan yang sudah memiliki pembayaran tidak boleh dihapus
     * untuk menjaga integritas riwayat pembayaran.
     *
     * Body: { "nomor_tagihan": "..." }
     */
    public function delete(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'nomor_tagihan' => ['required', 'string', 'max:30'],
        ], [
            'nomor_tagihan.required' => 'Nomor tagihan wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $nomorTagihan = $request->json('nomor_tagihan');

        try {
            $tagihan = Tagihan::where('nomor_tagihan', $nomorTagihan)->first();

            if (! $tagihan) {
                return ApiResponse::error('Tagihan tidak ditemukan.', null, 404, ErrorCode::DATA_NOT_FOUND);
            }

            if ((float) $tagihan->nominal_terbayar > 0) {
                return ApiResponse::error('Tagihan tidak dapat dihapus karena sudah memiliki pembayaran.', null, 400);
            }

            $tagihan->delete();

            return ApiResponse::success(['message' => 'Tagihan berhasil dihapus.']);
        } catch (\Throwable $e) {
            Log::error('TagihanController: gagal menghapus tagihan.', ['nomor_tagihan' => $nomorTagihan, 'message' => $e->getMessage()]);

            return ApiResponse::error('Gagal menghapus tagihan', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Buat tagihan PMB (Penerimaan Mahasiswa Baru).
     *
     * Body: { "npm": "...", "tahun_akademik": "20241", "nama_mahasiswa": "...",
     *         "nama_fakultas": "...", "kode_prodi": "...", "nama_program_studi": "...",
     *         "jumlah_tagihan": 5000000, "id_kelas_perkuliahan": "..." }
     */
    public function create(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'npm'                  => ['required', 'string', 'max:30'],
            'tahun_akademik'       => ['required', 'string', 'regex:/^\d{4}[12]$/'],
            'nama_mahasiswa'       => ['required', 'string', 'max:255'],
            'nama_fakultas'        => ['required', 'string', 'max:255'],
            'kode_prodi'           => ['required', 'string', 'max:8'],
            'nama_program_studi'   => ['required', 'string', 'max:255'],
            'id_kelas_perkuliahan' => ['required', 'string', 'max:255'],
            'jumlah_tagihan'       => ['required', 'numeric', 'min:0'],
        ], [
            'npm.required'                  => 'NPM wajib diisi.',
            'tahun_akademik.required'       => 'Tahun akademik wajib diisi.',
            'tahun_akademik.regex'          => 'Format tahun akademik tidak valid (YYYY1/YYYY2).',
            'nama_mahasiswa.required'       => 'Nama mahasiswa wajib diisi.',
            'nama_fakultas.required'        => 'Nama fakultas wajib diisi.',
            'kode_prodi.required'           => 'Kode prodi wajib diisi.',
            'kode_prodi.max'                => 'Kode prodi maksimal :max karakter.',
            'nama_program_studi.required'   => 'Nama program studi wajib diisi.',
            'id_kelas_perkuliahan.required' => 'ID kelas perkuliahan wajib diisi.',
            'jumlah_tagihan.required'       => 'Jumlah tagihan wajib diisi.',
            'jumlah_tagihan.numeric'        => 'Jumlah tagihan harus berupa angka.',
            'jumlah_tagihan.min'            => 'Jumlah tagihan minimal 0.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $data = $request->json()->all();

        try {
            // id_record_tagihan & nomor_tagihan dibatasi varchar(30) di tabel tagihan.
            $timestamp = now()->format('YmdHis');
            $rand      = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            $tagihan = Tagihan::create([
                'id_record_tagihan'    => 'PMBR' . $timestamp . $rand,
                'nomor_tagihan'        => 'PMB' . $timestamp . $rand,
                'npm'                  => $data['npm'],
                'nama_mahasiswa'       => $data['nama_mahasiswa'],
                'nama_fakultas'        => $data['nama_fakultas'],
                'kode_program_studi'   => $data['kode_prodi'],
                'nama_program_studi'   => $data['nama_program_studi'],
                'id_kelas_perkuliahan' => $data['id_kelas_perkuliahan'],
                'tahun_akademik'       => $data['tahun_akademik'],
                'total_tagihan'        => $data['jumlah_tagihan'],
                'nominal_ditagih'      => $data['jumlah_tagihan'],
                'nominal_terbayar'     => 0,
                'jenis_tagihan'        => 'PMB',
                'status_aktif'         => 'Y',
                'waktu_berakhir'       => now()->addYear(),
            ]);

            return ApiResponse::success([
                'message'       => 'Tagihan berhasil dibuat.',
                'nomor_tagihan' => $tagihan->nomor_tagihan,
            ]);
        } catch (\Throwable $e) {
            Log::error('TagihanController: gagal membuat tagihan PMB.', ['npm' => $data['npm'], 'message' => $e->getMessage()]);

            return ApiResponse::error('Gagal membuat tagihan', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    /**
     * Format standar data tagihan untuk response.
     */
    private function formatTagihan(Tagihan $t): array
    {
        return [
            'id_record_tagihan'  => $t->id_record_tagihan,
            'nomor_tagihan'      => $t->nomor_tagihan,
            'npm'                => $t->npm,
            'nama_mahasiswa'     => $t->nama_mahasiswa,
            'nama_fakultas'      => $t->nama_fakultas,
            'nama_program_studi' => $t->nama_program_studi,
            'nama_kelas'         => $t->nama_kelas_perkuliahan,
            'tahun_akademik'     => $t->tahun_akademik,
            'waktu_berakhir'     => $t->waktu_berakhir?->format('Y-m-d H:i:s'),
            'detail_tagihan'     => $t->detail_tagihan,
            'total_tagihan'      => (float) $t->total_tagihan,
            'total_potongan'     => (float) $t->total_potongan,
            'nominal_ditagih'    => (float) $t->nominal_ditagih,
            'nominal_terbayar'   => (float) $t->nominal_terbayar,
            'jenis_tagihan'      => $t->jenis_tagihan,
            'status_aktif'       => $t->status_aktif,
            'status_lunas'       => $this->isLunas($t),
        ];
    }

    /**
     * Cek status lunas — nominal_terbayar >= nominal_ditagih.
     */
    private function isLunas(Tagihan $t): bool
    {
        return (float) $t->nominal_terbayar >= (float) $t->nominal_ditagih;
    }
}
