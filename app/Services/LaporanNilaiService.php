<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class LaporanNilaiService
{
    /**
     * Laporan input nilai — jadwal sebagai acuan.
     *
     *   - Mulai dari jadwal perkuliahan dengan TA dan prodi yang diminta.
     *   - KRS dijembatani lewat jadwal lama (TA=0) dengan mata_kuliah_id
     *     yang sama (karena KRS terhubung ke jadwal_id yang TA-nya masih 0).
     *   - Nama dosen langsung dari jadwal (dosen_id > 0).
     */
    public function getLaporan(string $tahunAkademik, ?string $kodeProdi = null): array
    {
        $prodiFilter = $kodeProdi ? "AND j.kode_program_studi = '{$kodeProdi}'" : '';

        // ---------- Ringkasan ----------
        $ringkasanRow = DB::connection('db_siade')->selectOne("
            SELECT
                COUNT(DISTINCT j.id)   AS total_jadwal,
                COUNT(k.id)            AS total_mahasiswa,
                SUM(CASE WHEN k.nilai_angka IS NOT NULL THEN 1 ELSE 0 END) AS total_sudah_dinilai,
                SUM(CASE WHEN k.nilai_angka IS NULL     THEN 1 ELSE 0 END) AS total_belum_dinilai
            FROM tbl_jadwal_perkuliahan j
            LEFT JOIN tbl_jadwal_perkuliahan j0
                ON  j0.mata_kuliah_id      = j.mata_kuliah_id
                AND j0.kode_program_studi  = j.kode_program_studi
                AND j0.tahun_akademik      = '0'
            LEFT JOIN tbl_mahasiswa_krs k
                ON  k.jadwal_id            = j0.id
                AND k.kode_tahun_akademik  = ?
            WHERE j.tahun_akademik = ?
              AND j.dosen_id > 0
              {$prodiFilter}
        ", [$tahunAkademik, $tahunAkademik]);

        if (! $ringkasanRow || $ringkasanRow->total_jadwal == 0) {
            throw new \RuntimeException(
                'Data jadwal tidak ditemukan untuk tahun akademik ' . $tahunAkademik
                . ($kodeProdi ? ' dan program studi ' . $kodeProdi : '') . '.'
            );
        }

        $totalJadwal    = (int) $ringkasanRow->total_jadwal;
        $totalMahasiswa = (int) ($ringkasanRow->total_mahasiswa ?? 0);
        $totalSudah     = (int) ($ringkasanRow->total_sudah_dinilai ?? 0);
        $totalBelum     = (int) ($ringkasanRow->total_belum_dinilai ?? 0);

        // ---------- Rincian per jadwal ----------
        $rincianRows = DB::connection('db_siade')->select("
            SELECT
                j.id            AS jadwal_id,
                j.kelompok,
                j.kode_program_studi,
                j.dosen_id,
                mk.kode_mata_kuliah,
                mk.nama_mata_kuliah_idn AS nama_mata_kuliah,
                mk.sks_mata_kuliah,
                mk.semester,
                COUNT(k.id)     AS total_mahasiswa,
                SUM(CASE WHEN k.nilai_angka IS NOT NULL THEN 1 ELSE 0 END) AS sudah_dinilai,
                SUM(CASE WHEN k.nilai_angka IS NULL     THEN 1 ELSE 0 END) AS belum_dinilai
            FROM tbl_jadwal_perkuliahan j
            LEFT JOIN master_kurikulum_matakuliah mk
                ON mk.id = j.mata_kuliah_id
            LEFT JOIN tbl_jadwal_perkuliahan j0
                ON  j0.mata_kuliah_id      = j.mata_kuliah_id
                AND j0.kode_program_studi  = j.kode_program_studi
                AND j0.tahun_akademik      = '0'
            LEFT JOIN tbl_mahasiswa_krs k
                ON  k.jadwal_id            = j0.id
                AND k.kode_tahun_akademik  = ?
            WHERE j.tahun_akademik = ?
              AND j.dosen_id > 0
              {$prodiFilter}
            GROUP BY j.id, j.kelompok, j.kode_program_studi, j.dosen_id,
                     mk.kode_mata_kuliah, mk.nama_mata_kuliah_idn,
                     mk.sks_mata_kuliah, mk.semester
            ORDER BY mk.semester, mk.nama_mata_kuliah_idn, j.kelompok
        ", [$tahunAkademik, $tahunAkademik]);

        // ---------- Ambil nama dosen ----------
        $dosenIds = array_unique(array_column($rincianRows, 'dosen_id'));
        $pegawai = DB::connection('db_siade_old')
            ->table('pegawai')
            ->whereIn('id', $dosenIds)
            ->select('id', 'nama_lengkap', 'nidn')
            ->get()
            ->keyBy('id');

        // ---------- Ambil detail mahasiswa per jadwal ----------
        $mahasiswaByJadwal = $this->loadMahasiswa($tahunAkademik, $kodeProdi);

        // ---------- Gabung ----------
        $rincian = [];
        $jl = $js = $jb = $jk = 0; // jumlah lengkap, sebagian, belum, kosong

        foreach ($rincianRows as $row) {
            $totalMhs = (int) $row->total_mahasiswa;
            $sudah    = (int) $row->sudah_dinilai;
            $belum    = (int) $row->belum_dinilai;
            $dosen    = $pegawai->get($row->dosen_id);

            $status = match (true) {
                $totalMhs === 0   => 'tidak_ada_peserta',
                $belum === 0      => 'lengkap',
                $sudah === 0      => 'belum',
                default           => 'sebagian',
            };

            match ($status) {
                'lengkap'             => $jl++,
                'sebagian'            => $js++,
                'belum'               => $jb++,
                'tidak_ada_peserta'   => $jk++,
                default               => null,
            };

            $rincian[] = [
                'jadwal_id'            => (int) $row->jadwal_id,
                'kelompok'             => $row->kelompok ?? '',
                'kode_mata_kuliah'     => $row->kode_mata_kuliah ?? '',
                'nama_mata_kuliah'     => $row->nama_mata_kuliah ?? '',
                'sks_mata_kuliah'      => (int) ($row->sks_mata_kuliah ?? 0),
                'semester'             => (int) ($row->semester ?? 0),
                'dosen_id'             => (int) $row->dosen_id,
                'nama_dosen'           => $dosen->nama_lengkap ?? '',
                'nidn_dosen'           => $dosen->nidn ?? '',
                'kode_program_studi'   => $row->kode_program_studi ?? '',
                'total_mahasiswa'      => $totalMhs,
                'jumlah_sudah_dinilai' => $sudah,
                'jumlah_belum_dinilai' => $belum,
                'status_input_nilai'   => $status,
                'mahasiswa'            => $mahasiswaByJadwal[$row->jadwal_id] ?? [],
            ];
        }

        return [
            'tahun_akademik'     => $tahunAkademik,
            'kode_program_studi' => $kodeProdi,
            'ringkasan'          => [
                'total_jadwal'             => $totalJadwal,
                'total_mahasiswa'          => $totalMahasiswa,
                'total_sudah_dinilai'      => $totalSudah,
                'total_belum_dinilai'      => $totalBelum,
                'persentase_input'         => $totalMahasiswa > 0
                    ? round(($totalSudah / $totalMahasiswa) * 100, 2)
                    : 0,
                'jumlah_lengkap'           => $jl,
                'jumlah_sebagian'          => $js,
                'jumlah_belum'             => $jb,
                'jumlah_tidak_ada_peserta' => $jk,
            ],
            'rincian'            => $rincian,
        ];
    }

    /**
     * Ambil daftar mahasiswa (beserta nilai) untuk setiap jadwal.
     *
     * @return array<int, array>  jadwal_id → [mahasiswa...]
     */
    protected function loadMahasiswa(string $tahunAkademik, ?string $kodeProdi): array
    {
        $prodiFilter = $kodeProdi ? "AND j20232.kode_program_studi = '{$kodeProdi}'" : '';

        $rows = DB::connection('db_siade')->select("
            SELECT
                j20232.id        AS jadwal_id,
                m.npm,
                m.nama_mahasiswa,
                k.nilai_angka,
                k.nilai_huruf,
                k.nilai_bobot
            FROM tbl_jadwal_perkuliahan j20232
            JOIN tbl_jadwal_perkuliahan j0
                ON  j0.mata_kuliah_id      = j20232.mata_kuliah_id
                AND j0.kode_program_studi  = j20232.kode_program_studi
                AND j0.tahun_akademik      = '0'
            JOIN tbl_mahasiswa_krs k
                ON  k.jadwal_id            = j0.id
                AND k.kode_tahun_akademik  = ?
            JOIN master_mahasiswa m
                ON m.npm = k.npm
            WHERE j20232.tahun_akademik = ?
              AND j20232.dosen_id > 0
              {$prodiFilter}
            ORDER BY m.nama_mahasiswa
        ", [$tahunAkademik, $tahunAkademik]);

        $result = [];
        foreach ($rows as $row) {
            $result[$row->jadwal_id][] = [
                'npm'            => $row->npm,
                'nama_mahasiswa' => $row->nama_mahasiswa,
                'nilai_angka'    => $row->nilai_angka !== null ? (float) $row->nilai_angka : null,
                'nilai_huruf'    => $row->nilai_huruf ?? null,
                'nilai_bobot'    => $row->nilai_bobot !== null ? (float) $row->nilai_bobot : null,
            ];
        }

        return $result;
    }
}
