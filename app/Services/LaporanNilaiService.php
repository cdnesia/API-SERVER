<?php

namespace App\Services;

use App\Models\KRS;
use Illuminate\Support\Collection;

class LaporanNilaiService
{
    /**
     * Ambil laporan input nilai per tahun akademik.
     *
     * Query dari sisi KRS (pakai kode_tahun_akademik), lalu dikelompokkan
     * per jadwal.  Pendekatan ini memastikan data tetap muncul meskipun
     * kolom tahun_akademik di tabel jadwal masih 0 (belum disinkronkan).
     *
     * @param  string       $tahunAkademik  Kode tahun akademik, contoh: "20241"
     * @param  string|null  $kodeProdi      Filter opsional kode program studi
     * @return array
     */
    public function getLaporan(string $tahunAkademik, ?string $kodeProdi = null): array
    {
        // Query dari sisi KRS → filter by kode_tahun_akademik
        $krsQuery = KRS::with(['jadwal.mataKuliah', 'jadwal.dosen', 'mahasiswa'])
            ->where('kode_tahun_akademik', $tahunAkademik)
            ->whereHas('jadwal', function ($q) use ($kodeProdi) {
                if ($kodeProdi !== null) {
                    $q->where('kode_program_studi', $kodeProdi);
                }
            });

        $krsItems = $krsQuery->get();

        if ($krsItems->isEmpty()) {
            throw new \RuntimeException(
                'Data KRS tidak ditemukan untuk tahun akademik ' . $tahunAkademik
                . ($kodeProdi ? ' dan program studi ' . $kodeProdi : '') . '.'
            );
        }

        // Kelompokkan per jadwal_id
        $grouped = $krsItems->groupBy('jadwal_id');

        $laporan = $grouped->map(function (Collection $krsGroup) {
            $first    = $krsGroup->first();
            $jadwal   = $first->jadwal;
            $mk       = $jadwal?->mataKuliah;
            $dosen    = $jadwal?->dosen;

            $totalMahasiswa    = $krsGroup->count();
            $sudahDinilai      = $krsGroup->whereNotNull('nilai_angka');
            $jumlahSudah       = $sudahDinilai->count();
            $jumlahBelum       = $totalMahasiswa - $jumlahSudah;

            $daftarMahasiswa = $krsGroup
                ->map(fn (KRS $krs) => [
                    'npm'            => $krs->npm,
                    'nama_mahasiswa' => $krs->mahasiswa?->nama_mahasiswa ?? '',
                    'nilai_angka'    => $krs->nilai_angka !== null ? (float) $krs->nilai_angka : null,
                    'nilai_huruf'    => $krs->nilai_huruf ?? null,
                    'nilai_bobot'    => $krs->nilai_bobot !== null ? (float) $krs->nilai_bobot : null,
                ])
                ->values()
                ->toArray();

            $status = match (true) {
                $totalMahasiswa === 0     => 'tidak_ada_peserta',
                $jumlahBelum === 0        => 'lengkap',
                $jumlahSudah === 0        => 'belum',
                default                   => 'sebagian',
            };

            return [
                'jadwal_id'              => $first->jadwal_id,
                'kelompok'               => $jadwal?->kelompok ?? '',
                'kode_mata_kuliah'       => $mk->kode_mata_kuliah ?? '',
                'nama_mata_kuliah'       => $mk->nama_mata_kuliah_idn ?? '',
                'sks_mata_kuliah'        => (int) ($mk->sks_mata_kuliah ?? 0),
                'semester'               => (int) ($mk->semester ?? 0),
                'dosen_id'               => $dosen->id ?? null,
                'nama_dosen'             => $dosen->nama_lengkap ?? '',
                'nidn_dosen'             => $dosen->nidn ?? '',
                'kode_program_studi'     => $jadwal?->kode_program_studi ?? '',
                'total_mahasiswa'        => $totalMahasiswa,
                'jumlah_sudah_dinilai'   => $jumlahSudah,
                'jumlah_belum_dinilai'   => $jumlahBelum,
                'status_input_nilai'     => $status,
                'mahasiswa'              => $daftarMahasiswa,
            ];
        });

        // Hitung ringkasan
        $totalJadwal        = $laporan->count();
        $totalMahasiswa     = $laporan->sum('total_mahasiswa');
        $totalSudahDinilai  = $laporan->sum('jumlah_sudah_dinilai');
        $totalBelumDinilai  = $laporan->sum('jumlah_belum_dinilai');
        $totalLengkap       = $laporan->where('status_input_nilai', 'lengkap')->count();
        $totalSebagian      = $laporan->where('status_input_nilai', 'sebagian')->count();
        $totalBelum         = $laporan->where('status_input_nilai', 'belum')->count();
        $totalTanpaPeserta  = $laporan->where('status_input_nilai', 'tidak_ada_peserta')->count();

        return [
            'tahun_akademik'        => $tahunAkademik,
            'kode_program_studi'    => $kodeProdi,
            'ringkasan'             => [
                'total_jadwal'             => $totalJadwal,
                'total_mahasiswa'          => $totalMahasiswa,
                'total_sudah_dinilai'      => $totalSudahDinilai,
                'total_belum_dinilai'      => $totalBelumDinilai,
                'persentase_input'         => $totalMahasiswa > 0
                    ? round(($totalSudahDinilai / $totalMahasiswa) * 100, 2)
                    : 0,
                'jumlah_lengkap'           => $totalLengkap,
                'jumlah_sebagian'          => $totalSebagian,
                'jumlah_belum'             => $totalBelum,
                'jumlah_tidak_ada_peserta' => $totalTanpaPeserta,
            ],
            'rincian'          => $laporan->values()->toArray(),
        ];
    }
}
