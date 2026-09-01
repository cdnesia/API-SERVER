<?php

namespace App\Services;

use App\Models\KRS;
use App\Models\Mahasiswa;
use Illuminate\Support\Collection;

class AkademikService
{
    protected ?DosenService $dosenService = null;

    /**
     * Lazy-load DosenService (dibuat saat pertama kali dipakai).
     */
    protected function dosen(): DosenService
    {
        return $this->dosenService ??= new DosenService;
    }

    /**
     * Ambil data profil mahasiswa beserta fakultas & program studi.
     *
     * @throws \RuntimeException  bila NPM tidak ditemukan.
     */
    public function getStudent(string $npm): array
    {
        $mhs = Mahasiswa::with('programStudi.fakultas')
            ->where('npm', $npm)
            ->first();

        if (! $mhs) {
            throw new \RuntimeException('Data mahasiswa tidak ditemukan.');
        }

        // Resolve PA (Pembimbing Akademik)
        $dosenPa  = $mhs->pa_id ? $this->dosen()->getById((int) $mhs->pa_id) : null;
        $namaPa   = $dosenPa['nama_lengkap'] ?? ($mhs->pa_id ?? '');

        // Resolve Dekan
        $dekanId   = $mhs->programStudi?->fakultas?->dekan_id;
        $dosenDekan = $dekanId ? $this->dosen()->getById((int) $dekanId) : null;
        $namaDekan  = $dosenDekan['nama_lengkap'] ?? ($dekanId ?? '');
        $nidnDekan  = $dosenDekan['nidn'] ?? ($dekanId ?? '');

        return [
            'nama_mahasiswa'     => $mhs->nama_mahasiswa,
            'npm'                => $mhs->npm,
            'tahun_angkatan'     => $mhs->tahun_angkatan ?? '',
            'nama_fakultas'      => $mhs->programStudi?->fakultas?->nama_fakultas_idn ?? '',
            'nama_program_studi' => $mhs->programStudi?->nama_program_studi_idn ?? '',
            'nama_dekan'         => $namaDekan,
            'nidn_dekan'         => $nidnDekan,
            'nidn_pa'            => $dosenPa['nidn'] ?? ($mhs->pa_id ?? ''),
            'dosen_pa'           => $namaPa,
        ];
    }

    // ──────────────────────────────────────────────
    //  KHS — satu semester
    // ──────────────────────────────────────────────

    /**
     * Ambil data KHS mahasiswa.
     *
     * - Tanpa $periode: semua semester dari tahun angkatan s/d sekarang.
     * - Dengan $periode: hanya semester itu.
     *
     * Return (jika $periode null):
     *  [
     *    'semester' => [ [...] per periode ],
     *    'ipk' => '3.45',
     *  ]
     *
     * Return (jika $periode diisi):
     *  [
     *    'krs'      => [...],
     *    'metadata' => ['ips' => '3.50', 'ipk' => '3.45']
     *  ]
     */
    public function getKhs(string $npm, ?string $periode = null): array
    {
        // Ambil data mahasiswa untuk dapat tahun_angkatan
        $mhs = Mahasiswa::where('npm', $npm)->first();

        if (! $mhs) {
            throw new \RuntimeException('Data mahasiswa tidak ditemukan.');
        }

        $tahunAngkatan = $mhs->tahun_angkatan ?: date('Y') . '1';

        $items = $this->queryKrs($npm)->orderBy('kode_tahun_akademik')->get();

        $allPeriods = $this->generatePeriods($tahunAngkatan);
        $grouped   = $items->groupBy('kode_tahun_akademik');
        $ipk       = $this->getIpk($npm);

        // Jika periode spesifik → return single
        if ($periode !== null) {
            return $this->formatKhsSingle($grouped->get($periode, collect()), $periode, $ipk);
        }

        // Semua semester
        $semester = collect($allPeriods)->map(function ($label, $code) use ($grouped, $ipk) {
            $entries = $grouped->get($code, collect());
            $mapped  = $this->mapKrsItems($entries);

            $ips = $mapped['total_sks'] > 0
                ? round($mapped['total_bobot'] / $mapped['total_sks'], 2)
                : 0;

            return [
                'periode'   => $code,
                'label'     => $label,
                'krs'       => $mapped['items']->toArray(),
                'total_sks' => $mapped['total_sks'],
                'ips'       => number_format($ips, 2),
            ];
        })->values()->toArray();

        return [
            'semester' => $semester,
            'ipk'      => number_format($ipk, 2),
        ];
    }

    /**
     * Format single periode KHS.
     */
    protected function formatKhsSingle(Collection $items, string $periode, float $ipk): array
    {
        $mapped = $this->mapKrsItems($items);

        $ips = $mapped['total_sks'] > 0
            ? round($mapped['total_bobot'] / $mapped['total_sks'], 2)
            : 0;

        return [
            'krs'      => $mapped['items']->toArray(),
            'metadata' => [
                'ips' => number_format($ips, 2),
                'ipk' => number_format($ipk, 2),
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  KRS — rencana studi (dengan jadwal & dosen)
    // ──────────────────────────────────────────────

    /**
     * Ambil data KRS mahasiswa.
     *
     * - Tanpa $periode: semua semester dari tahun angkatan s/d sekarang.
     * - Dengan $periode: hanya semester itu.
     *
     * Return (jika $periode null):
     *  [
     *    'semester' => [ [...] per periode ],
     *    'total_sks' => 40,
     *  ]
     *
     * Return (jika $periode diisi):
     *  [
     *    'periode'  => '20231',
     *    'krs'      => [...],
     *    'total_sks'=> 20,
     *  ]
     */
    public function getKrs(string $npm, ?string $periode = null): array
    {
        // Ambil data mahasiswa untuk dapat tahun_angkatan
        $mhs = Mahasiswa::where('npm', $npm)->first();

        if (! $mhs) {
            throw new \RuntimeException('Data mahasiswa tidak ditemukan.');
        }

        $tahunAngkatan = $mhs->tahun_angkatan ?: date('Y') . '1';

        // Query semua KRS dengan eager load jadwal lengkap
        $query = KRS::with(['jadwal.mataKuliah', 'jadwal.hari', 'jadwal.dosen', 'mataKuliah'])
            ->where('npm', $npm);

        if ($periode !== null) {
            $query->where('kode_tahun_akademik', $periode);
        }

        $items = $query->orderBy('kode_tahun_akademik')->get();

        // Generate semua periode dari tahun angkatan s/d sekarang
        $allPeriods = $this->generatePeriods($tahunAngkatan);

        // Kelompokkan KRS per periode
        $grouped = $items->groupBy('kode_tahun_akademik');

        // Jika periode spesifik → return single
        if ($periode !== null) {
            return $this->formatKrsSingle($grouped->get($periode, collect()), $periode);
        }

        // Jika semua periode → return array of semesters
        $grandTotalSks = 0;

        $semester = collect($allPeriods)->map(function ($periodLabel, $periodCode) use ($grouped, &$grandTotalSks) {
            $entries = $grouped->get($periodCode, collect());

            $mapped = $this->mapKrsWithJadwal($entries);
            $grandTotalSks += $mapped['total_sks'];

            return [
                'periode'   => $periodCode,
                'label'     => $periodLabel,
                'krs'       => $mapped['items'],
                'total_sks' => $mapped['total_sks'],
            ];
        })->values()->toArray();

        return [
            'semester'  => $semester,
            'total_sks' => $grandTotalSks,
        ];
    }

    // ──────────────────────────────────────────────
    //  Period generator
    // ──────────────────────────────────────────────

    /**
     * Generate semua kode periode dari tahunAngkatan sampai sekarang.
     *
     * Contoh: "20221" → ["20221" => "2022 Ganjil", "20222" => "2022 Genap", ...]
     */
    protected function generatePeriods(string $tahunAngkatan): array
    {
        $startYear   = (int) substr($tahunAngkatan, 0, 4);
        $startSem    = (int) substr($tahunAngkatan, -1); // 1=Ganjil, 2=Genap
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');

        // Semester saat ini: Jan-Jun = Genap (tahun lalu), Jul-Des = Ganjil (tahun ini)
        $currentSem = $currentMonth <= 6 ? 2 : 1;
        $endYear = $currentMonth <= 6 ? $currentYear : $currentYear;

        // Adjust: jika currentSem=2 maka tahunnya tahun sekarang, bukan tahun lalu
        // Format: 20261 = 2026 Ganjil, 20252 = 2025 Genap
        // Bulan 7-12 → Ganjil(1) tahun ini, bulan 1-6 → Genap(2) tahun ini
        // Tapi ini ambigu. Pakai pendekatan: current period adalah yang terbesar di data
        // + fallback ke perhitungan dari tanggal sekarang.
        // Untuk amannya kita bisa ambil dari master_tahun_akademik.
        $maxPeriod = $this->getCurrentPeriod($endYear, $currentSem);

        $periods   = [];
        $year      = $startYear;
        $sem       = $startSem;

        while (true) {
            $code  = sprintf('%d%d', $year, $sem);
            $label = $year . '/' . ($year + 1) . ' ' . ($sem === 1 ? 'Ganjil' : 'Genap');
            $periods[$code] = $label;

            if ($code === $maxPeriod) {
                break;
            }

            // Maju 1 semester
            if ($sem === 1) {
                $sem = 2;
            } else {
                $sem = 1;
                $year++;
            }
        }

        return $periods;
    }

    /**
     * Dapatkan kode periode terbaru (ambil dari DB kalau ada, fallback ke tanggal).
     */
    protected function getCurrentPeriod(int $year, int $sem): string
    {
        try {
            $max = \Illuminate\Support\Facades\DB::connection('db_siade')
                ->table('tbl_mahasiswa_krs')
                ->max('kode_tahun_akademik');

            if ($max) {
                return $max;
            }
        } catch (\Throwable) {
            // fallback
        }

        return sprintf('%d%d', $year, $sem);
    }

    // ──────────────────────────────────────────────
    //  Format helpers
    // ──────────────────────────────────────────────

    /**
     * Map collection KRS ke array dengan info jadwal (hari, jam, dosen).
     */
    protected function mapKrsWithJadwal(Collection $items): array
    {
        $totalSks = 0;

        $mapped = $items->map(function (KRS $item) use (&$totalSks) {
            $mk  = $item->jadwal?->mataKuliah ?? $item->mataKuliah;
            $sks = (int) ($mk->sks_mata_kuliah ?? 0);
            $totalSks += $sks;

            return [
                'sks_matakuliah'   => $sks,
                'kode_mata_kuliah' => $mk->kode_mata_kuliah ?? '',
                'nama_mata_kuliah' => $mk->nama_mata_kuliah_idn ?? '',
                'nama_hari'        => $item->jadwal?->hari?->nama_hari ?? '',
                'jam_mulai'        => $item->jadwal?->jam_mulai ?? '',
                'jam_selesai'      => $item->jadwal?->jam_selesai ?? '',
                'nama_dosen'       => $item->jadwal?->dosen?->nama_lengkap ?? '',
            ];
        });

        return [
            'items'     => $mapped->toArray(),
            'total_sks' => $totalSks,
        ];
    }

    /**
     * Format single periode untuk return getKrs($npm, $periode).
     */
    protected function formatKrsSingle(Collection $items, string $periode): array
    {
        $mapped = $this->mapKrsWithJadwal($items);

        return [
            'periode'   => $periode,
            'krs'       => $mapped['items'],
            'total_sks' => $mapped['total_sks'],
        ];
    }

    // ──────────────────────────────────────────────
    //  Transkrip / semua semester
    // ──────────────────────────────────────────────

    /**
     * Ambil seluruh data KRS mahasiswa dari semua semester (untuk transkrip).
     *
     * Return:
     *  [
     *    'periode' => [
     *       'semester' => '20231',
     *       'label'    => '2023 Ganjil',
     *       'krs'      => [...],
     *       'ips'      => '3.50',
     *       'total_sks'=> 20,
     *     ],
     *     ...
     *  ]
     */
    public function getTranskrip(string $npm): array
    {
        $items = $this->queryKrs($npm)->orderBy('kode_tahun_akademik')->get();

        if ($items->isEmpty()) {
            throw new \RuntimeException('Data transkrip tidak ditemukan.');
        }

        return $items
            ->groupBy('kode_tahun_akademik')
            ->map(function (Collection $group) {
                $mapped = $this->mapKrsItems($group);
                $tahun  = substr($group->first()->kode_tahun_akademik, 0, 4);
                $akhiran = (int) substr($group->first()->kode_tahun_akademik, -1);

                return [
                    'semester'  => $group->first()->kode_tahun_akademik,
                    'label'     => $tahun . '/' . ($tahun + 1) . ' ' . ($akhiran % 2 === 0 ? 'Genap' : 'Ganjil'),
                    'krs'       => $mapped['items']->toArray(),
                    'ips'       => number_format(
                        $mapped['total_sks'] > 0
                            ? round($mapped['total_bobot'] / $mapped['total_sks'], 2)
                            : 0,
                        2,
                    ),
                    'total_sks' => $mapped['total_sks'],
                ];
            })
            ->values()
            ->toArray();
    }

    // ──────────────────────────────────────────────
    //  IPK (indeks prestasi kumulatif)
    // ──────────────────────────────────────────────

    /**
     * Hitung IPK dari seluruh semester.
     *
     * Rumus: Σ(nilai_bobot × sks) / Σ(sks)
     */
    public function getIpk(string $npm): float
    {
        $items = $this->queryKrs($npm)->get();

        $totalMutu = 0;
        $totalSks  = 0;

        foreach ($items as $item) {
            $mk  = $this->resolveMataKuliah($item);
            $sks = (int) ($mk->sks_mata_kuliah ?? 0);

            if ($sks === 0) {
                continue;
            }

            $totalMutu += (float) ($item->nilai_bobot ?? 0) * $sks;
            $totalSks  += $sks;
        }

        return $totalSks > 0 ? round($totalMutu / $totalSks, 2) : 0;
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    /**
     * Base query KRS dengan eager-load jalur matakuliah.
     */
    protected function queryKrs(string $npm, ?string $periode = null): \Illuminate\Database\Eloquent\Builder
    {
        $query = KRS::with(['jadwal.mataKuliah', 'mataKuliah'])
            ->where('npm', $npm);

        if ($periode !== null) {
            $query->where('kode_tahun_akademik', $periode);
        }

        return $query;
    }

    /**
     * Map Collection KRS ke array item matakuliah, sekaligus hitung
     * total SKS dan total mutu (nilai_bobot × sks) untuk IPS/IPK.
     *
     * @return array{items: Collection, total_sks: int, total_bobot: float}
     */
    protected function mapKrsItems(Collection $items): array
    {
        $totalMutu = 0;
        $totalSks  = 0;

        $mapped = $items->map(function (KRS $item) use (&$totalMutu, &$totalSks) {
            $mk    = $this->resolveMataKuliah($item);
            $sks   = (int) ($mk->sks_mata_kuliah ?? 0);
            $bobot = (float) ($item->nilai_bobot ?? 0);

            $totalMutu += $bobot * $sks;
            $totalSks  += $sks;

            return [
                'sks_matakuliah'   => $sks,
                'kode_mata_kuliah' => $mk->kode_mata_kuliah ?? '',
                'nama_mata_kuliah' => $mk->nama_mata_kuliah_idn ?? '',
                'nilai_angka'      => (float) ($item->nilai_angka ?? 0),
                'nilai_huruf'      => $item->nilai_huruf ?? '',
            ];
        });

        return [
            'items'       => $mapped,
            'total_sks'   => $totalSks,
            'total_bobot' => $totalMutu,
        ];
    }

    /**
     * Resolve mata kuliah: prioritas jadwal (lengkap dengan dosen),
     * fallback ke mata_kuliah_id langsung.
     */
    protected function resolveMataKuliah(KRS $item): object
    {
        return $item->jadwal?->mataKuliah
            ?? $item->mataKuliah
            ?? (object) ['sks_mata_kuliah' => 0, 'kode_mata_kuliah' => '', 'nama_mata_kuliah_idn' => ''];
    }
}
