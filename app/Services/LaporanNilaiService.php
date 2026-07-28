<?php

namespace App\Services;

use App\Models\JadwalPerkuliahan;
use App\Models\KRS;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LaporanNilaiService
{
    /**
     * Ambil laporan input nilai per tahun akademik.
     *
     * Menampilkan daftar mata kuliah yang dibuka pada tahun akademik tertentu,
     * beserta nama dosen pengajar, jumlah mahasiswa peserta, dan progres input nilai.
     *
     * @param  string       $tahunAkademik  Kode tahun akademik, contoh: "20241"
     * @param  string|null  $kodeProdi      Filter opsional kode program studi
     * @return array
     */
    public function getLaporan(string $tahunAkademik, ?string $kodeProdi = null): array
    {
        // Ambil semua jadwal yang aktif pada tahun akademik tersebut
        $jadwalQuery = JadwalPerkuliahan::with(['mataKuliah', 'dosen', 'krs.mahasiswa'])
            ->where('tahun_akademik', $tahunAkademik);

        if ($kodeProdi !== null) {
            $jadwalQuery->where('kode_program_studi', $kodeProdi);
        }

        $jadwals = $jadwalQuery->get();

        if ($jadwals->isEmpty()) {
            throw new \RuntimeException(
                'Data jadwal perkuliahan tidak ditemukan untuk tahun akademik ' . $tahunAkademik
                . ($kodeProdi ? ' dan program studi ' . $kodeProdi : '') . '.'
            );
        }

        $laporan = $jadwals->map(function (JadwalPerkuliahan $jadwal) {
            $mataKuliah = $jadwal->mataKuliah;
            $dosen      = $jadwal->dosen;
            $allKrs     = $jadwal->krs;

            // Mahasiswa yang terdaftar di KRS untuk jadwal ini
            $totalMahasiswa  = $allKrs->count();

            // Mahasiswa yang sudah mendapat nilai (nilai_angka tidak null)
            $sudahDinilai = $allKrs->filter(function (KRS $krs) {
                return $krs->nilai_angka !== null;
            });

            $jumlahSudahDinilai = $sudahDinilai->count();
            $jumlahBelumDinilai = $totalMahasiswa - $jumlahSudahDinilai;

            // Semua mahasiswa yang mengontrak (gabungan sudah + belum dinilai)
            $daftarMahasiswa = $allKrs
                ->map(fn (KRS $krs) => [
                    'npm'            => $krs->npm,
                    'nama_mahasiswa' => $krs->mahasiswa?->nama_mahasiswa ?? '',
                    'nilai_angka'    => $krs->nilai_angka !== null ? (float) $krs->nilai_angka : null,
                    'nilai_huruf'    => $krs->nilai_huruf ?? null,
                    'nilai_bobot'    => $krs->nilai_bobot !== null ? (float) $krs->nilai_bobot : null,
                ])
                ->values()
                ->toArray();

            // Status: "lengkap" bila semua sudah dinilai, "sebagian", "belum" bila 0
            $status = match (true) {
                $totalMahasiswa === 0                     => 'tidak_ada_peserta',
                $jumlahBelumDinilai === 0                 => 'lengkap',
                $jumlahSudahDinilai === 0                 => 'belum',
                default                                    => 'sebagian',
            };

            return [
                'jadwal_id'              => $jadwal->id,
                'kelompok'               => $jadwal->kelompok ?? '',
                'kode_mata_kuliah'       => $mataKuliah->kode_mata_kuliah ?? '',
                'nama_mata_kuliah'       => $mataKuliah->nama_mata_kuliah_idn ?? '',
                'sks_mata_kuliah'        => (int) ($mataKuliah->sks_mata_kuliah ?? 0),
                'semester'               => (int) ($mataKuliah->semester ?? 0),
                'dosen_id'               => $dosen->id ?? null,
                'nama_dosen'             => $dosen->nama_lengkap ?? '',
                'nidn_dosen'             => $dosen->nidn ?? '',
                'kode_program_studi'     => $jadwal->kode_program_studi,
                'total_mahasiswa'        => $totalMahasiswa,
                'jumlah_sudah_dinilai'   => $jumlahSudahDinilai,
                'jumlah_belum_dinilai'   => $jumlahBelumDinilai,
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
