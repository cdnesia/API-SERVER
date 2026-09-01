<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mahasiswa;
use App\Models\Tagihan;
use App\Services\BipotService;
use App\Services\TagihanService;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TagihanSppController extends Controller
{
    protected const JALUR_MASUK_RPL = [13, 16];

    protected const STATUS_AWAL_RPL     = 4;
    protected const STATUS_AWAL_REGULER = 1;

    public function __construct(
        protected BipotService $bipotService,
        protected TagihanService $tagihanService,
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $validator = $this->validateInput($request);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        try {
            $mahasiswa = $this->findMahasiswa($request->json('npm'));
            [$semester, $summary] = $this->computeSummary($mahasiswa, $request->json('tahun_akademik'));

            return ApiResponse::success($summary + ['semester' => $semester]);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 404, ErrorCode::DATA_NOT_FOUND);
        } catch (\Throwable $e) {
            Log::error('TagihanSppController: gagal menghitung rincian tagihan SPP.', [
                'npm'     => $request->json('npm'),
                'message' => $e->getMessage(),
            ]);

            return ApiResponse::error('Gagal menghitung rincian tagihan SPP', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    public function create(Request $request): JsonResponse
    {
        $validator = $this->validateInput($request);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        $npm           = $request->json('npm');
        $tahunAkademik = $request->json('tahun_akademik');

        try {
            $mahasiswa = $this->findMahasiswa($npm);

            $existing = $this->findExistingTagihan($mahasiswa->npm, $mahasiswa->kode_program_studi, $tahunAkademik);

            if ($existing) {
                return ApiResponse::success([
                    'message'       => 'Tagihan SPP untuk NPM, prodi, dan tahun akademik ini sudah ada, dilewati.',
                    'nomor_tagihan' => $existing->nomor_tagihan,
                    'data'          => $this->tagihanService->formatTagihan($existing),
                ]);
            }

            [, $summary] = $this->computeSummary($mahasiswa, $tahunAkademik);

            $tagihan = $this->tagihanService->create([
                'npm'                    => $mahasiswa->npm,
                'nama_mahasiswa'         => $mahasiswa->nama_mahasiswa,
                'kode_program_studi'     => $mahasiswa->kode_program_studi,
                'nama_program_studi'     => $mahasiswa->programStudi?->nama_program_studi_idn,
                'nama_fakultas'          => $mahasiswa->programStudi?->fakultas?->nama_fakultas_idn,
                'va_code'                => $mahasiswa->va_code,
                'id_kelas_perkuliahan'   => (string) $mahasiswa->program_kuliah_id,
                'nama_kelas_perkuliahan' => $mahasiswa->kelasPerkuliahan?->nama_program_perkuliahan,
                'tahun_akademik'         => $tahunAkademik,
                'jenis_tagihan'          => 'SPP',
                'total_tagihan'          => $summary['total_biaya'],
                'total_potongan'         => $summary['total_potongan'],
                'nominal_ditagih'        => $summary['nominal_ditagih'],
                'detail_tagihan'         => $summary['rincian']->all(),
            ]);

            return ApiResponse::success([
                'message'       => 'Tagihan SPP berhasil dibuat.',
                'nomor_tagihan' => $tagihan->nomor_tagihan,
                'data'          => $this->tagihanService->formatTagihan($tagihan),
            ]);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 404, ErrorCode::DATA_NOT_FOUND);
        } catch (\Throwable $e) {
            Log::error('TagihanSppController: gagal membuat tagihan SPP.', [
                'npm'     => $npm,
                'message' => $e->getMessage(),
            ]);

            return ApiResponse::error('Gagal membuat tagihan SPP', null, 500, ErrorCode::INTERNAL_ERROR);
        }
    }

    protected function validateInput(Request $request): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($request->json()->all(), [
            'npm'            => ['required', 'string', 'max:20'],
            'tahun_akademik' => ['required', 'string', 'regex:/^\d{4}[12]$/'],
        ], [
            'npm.required'            => 'NPM wajib diisi.',
            'tahun_akademik.required' => 'Tahun akademik wajib diisi.',
            'tahun_akademik.regex'    => 'Format tahun akademik tidak valid (YYYY1/YYYY2).',
        ]);
    }

    /**
     * Hitung semester berjalan dan rincian bipot (prodi + angkatan + kelas
     * kuliah + jalur masuk mahasiswa), lalu ringkas jadi total biaya /
     * potongan / nominal ditagih.
     *
     * @return array{0: int, 1: array}  [$semester, $summary]
     *
     * @throws \RuntimeException  jika rincian bipot tidak ditemukan
     */
    protected function computeSummary(Mahasiswa $mahasiswa, string $tahunAkademik): array
    {
        $semester = $this->resolveSemester($mahasiswa->tahun_angkatan, $tahunAkademik);

        $rincian = $this->bipotService->getRincianBiaya(
            $mahasiswa->kode_program_studi,
            $mahasiswa->tahun_angkatan,
            $semester,
            null,
            $mahasiswa->program_kuliah_id,
            $this->resolveStatusAwal($mahasiswa->jenis_pendaftaran_id),
        );

        if ($rincian->isEmpty()) {
            throw new \RuntimeException(
                "Rincian biaya bipot tidak ditemukan untuk prodi {$mahasiswa->kode_program_studi} angkatan {$mahasiswa->tahun_angkatan} semester {$semester}."
            );
        }

        return [$semester, $this->summarize($mahasiswa, $rincian)];
    }

    /**
     * Cek apakah tagihan SPP untuk npm + prodi + tahun akademik ini sudah
     * pernah dibuat, supaya create() tidak membuat duplikat.
     */
    protected function findExistingTagihan(string $npm, string $kodeProdi, string $tahunAkademik): ?Tagihan
    {
        return Tagihan::where('npm', $npm)
            ->where('jenis_tagihan', 'SPP')
            ->where('kode_program_studi', $kodeProdi)
            ->where('tahun_akademik', $tahunAkademik)
            ->first();
    }

    protected function findMahasiswa(string $npm): Mahasiswa
    {
        $mahasiswa = Mahasiswa::with('programStudi.fakultas', 'kelasPerkuliahan')
            ->where('npm', $npm)
            ->first();

        if (! $mahasiswa) {
            throw new \RuntimeException("Mahasiswa dengan NPM {$npm} tidak ditemukan.");
        }

        return $mahasiswa;
    }

    /**
     * Hitung semester berjalan (1, 2, 3, ...) dari selisih tahun_akademik
     * yang ditagih terhadap tahun_angkatan (tahun masuk) mahasiswa.
     *
     * Format kode tahun: {4 digit tahun}{1=ganjil|2=genap}, mis. "20211".
     */
    protected function resolveSemester(string $tahunAngkatan, string $tahunAkademik): int
    {
        $termIndex = fn (string $kodeTahun) => ((int) substr($kodeTahun, 0, 4)) * 2 + ((int) substr($kodeTahun, 4, 1)) - 1;

        $semester = $termIndex($tahunAkademik) - $termIndex($tahunAngkatan) + 1;

        if ($semester < 1) {
            throw new \RuntimeException(
                "Tahun akademik {$tahunAkademik} berada sebelum tahun masuk mahasiswa ({$tahunAngkatan})."
            );
        }

        return $semester;
    }

    /**
     * Terjemahkan jenis_pendaftaran_id (jalur masuk mahasiswa) ke kode
     * status_awal pada master_bipot_per_semester. Lihat catatan pada
     * JALUR_MASUK_RPL di atas untuk dasar mapping ini.
     */
    protected function resolveStatusAwal(?int $jenisPendaftaranId): int
    {
        return in_array($jenisPendaftaranId, self::JALUR_MASUK_RPL, true)
            ? self::STATUS_AWAL_RPL
            : self::STATUS_AWAL_REGULER;
    }

    /**
     * Ringkas rincian bipot menjadi total biaya, total potongan, dan nominal ditagih.
     */
    protected function summarize(Mahasiswa $mahasiswa, Collection $rincian): array
    {
        $items = $rincian->map(function ($item) {
            return [
                'id_bipot'   => $item->id_bipot,
                'nama_bipot' => $item->bipot?->nama_bipot,
                'trxid'      => (int) ($item->bipot?->trxid ?? 1),
                'semester'   => $item->semester,
                'nominal'    => (float) $item->nominal,
            ];
        });

        $totalBiaya    = $items->filter(fn ($i) => $i['trxid'] >= 0)->sum('nominal');
        $totalPotongan = $items->filter(fn ($i) => $i['trxid'] < 0)->sum(fn ($i) => abs($i['nominal']));

        return [
            'npm'             => $mahasiswa->npm,
            'nomor_tagihan'   => $mahasiswa->nomor_tagihan,
            'nama_mahasiswa'  => $mahasiswa->nama_mahasiswa,
            'kode_prodi'      => $mahasiswa->kode_program_studi,
            'total_biaya'     => $totalBiaya,
            'total_potongan'  => $totalPotongan,
            'nominal_ditagih' => max($totalBiaya - $totalPotongan, 0),
            'jumlah_item'     => $items->count(),
            'rincian'         => $items->values(),
        ];
    }
}
