<?php

namespace App\Services;

use App\Models\Pembayaran;
use App\Models\Tagihan;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TagihanService
{
    /**
     * Ambil semua tagihan mahasiswa berdasarkan NPM.
     *
     * @return Collection  Eloquent Collection of Tagihan
     */
    public function getByNpm(string $npm): Collection
    {
        return Tagihan::where('npm', $npm)
            ->orderBy('tahun_akademik', 'desc')
            ->get();
    }

    /**
     * Ambil tagihan aktif mahasiswa (status_aktif = Y).
     */
    public function getAktifByNpm(string $npm): Collection
    {
        return Tagihan::where('npm', $npm)
            ->where('status_aktif', 'Y')
            ->orderBy('waktu_berakhir', 'asc')
            ->get();
    }

    /**
     * Ambil satu tagihan berdasarkan nomor_tagihan.
     */
    public function getByNomor(string $nomorTagihan): ?Tagihan
    {
        return Tagihan::where('nomor_tagihan', $nomorTagihan)->first();
    }

    /**
     * Ambil satu tagihan berdasarkan id_record_tagihan.
     */
    public function getByIdRecord(string $idRecord): ?Tagihan
    {
        return Tagihan::with('pembayaran')
            ->where('id_record_tagihan', $idRecord)
            ->first();
    }

    /**
     * Ambil tagihan per periode akademik.
     */
    public function getByNpmAndPeriode(string $npm, string $tahunAkademik): Collection
    {
        return Tagihan::where('npm', $npm)
            ->where('tahun_akademik', $tahunAkademik)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Ambil tagihan dengan pembayaran (untuk detail).
     */
    public function getDetailByNpm(string $npm): Collection
    {
        return Tagihan::with('pembayaran')
            ->where('npm', $npm)
            ->orderBy('tahun_akademik', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Ringkasan tagihan per NPM, optional filter periode.
     *
     * @param  string      $npm
     * @param  string|null $tahunAkademik  filter periode, e.g. "20231"
     */
    public function getSummary(string $npm, ?string $tahunAkademik = null): array
    {
        $all = $tahunAkademik
            ? $this->getByNpmAndPeriode($npm, $tahunAkademik)
            : $this->getByNpm($npm);

        $totalTagihan  = 0;
        $totalTerbayar = 0;
        $tagihanAktif  = 0;

        foreach ($all as $t) {
            $totalTagihan  += (float) $t->nominal_ditagih;
            $totalTerbayar += (float) $t->nominal_terbayar;

            if ($t->status_aktif === 'Y') {
                $tagihanAktif++;
            }
        }

        return [
            'total_tagihan'  => number_format($totalTagihan, 2, '.', ''),
            'total_terbayar' => number_format($totalTerbayar, 2, '.', ''),
            'sisa'           => number_format($totalTagihan - $totalTerbayar, 2, '.', ''),
            'jumlah_tagihan' => $all->count(),
            'tagihan_aktif'  => $tagihanAktif,
            'tagihan'        => $all->map(fn ($t) => $this->formatTagihan($t)),
        ];
    }

    /**
     * Cek status lunas — apakah nominal_terbayar >= nominal_ditagih.
     */
    public function isLunas(Tagihan $tagihan): bool
    {
        return (float) $tagihan->nominal_terbayar >= (float) $tagihan->nominal_ditagih;
    }

    /**
     * Ambil history pembayaran untuk satu tagihan.
     */
    public function getPembayaran(string $idRecordTagihan): Collection
    {
        return Pembayaran::where('id_record_tagihan', $idRecordTagihan)
            ->orderBy('waktu_transaksi', 'desc')
            ->get();
    }

    /**
     * Ambil history pembayaran per NPM (semua tagihan).
     */
    public function getPembayaranByNpm(string $npm): Collection
    {
        $tagihanIds = Tagihan::where('npm', $npm)->pluck('id_record_tagihan');

        return Pembayaran::whereIn('id_record_tagihan', $tagihanIds)
            ->orderBy('waktu_transaksi', 'desc')
            ->get();
    }

    // ──────────────────────────────────────────────
    //  Format
    // ──────────────────────────────────────────────

    /**
     * Format standar data tagihan.
     */
    public function formatTagihan(Tagihan $t): array
    {
        return [
            'id_record_tagihan'   => $t->id_record_tagihan,
            'nomor_tagihan'       => $t->nomor_tagihan,
            'npm'                 => $t->npm,
            'nama_mahasiswa'      => $t->nama_mahasiswa,
            'nama_fakultas'       => $t->nama_fakultas,
            'nama_program_studi'  => $t->nama_program_studi,
            'nama_kelas'          => $t->nama_kelas_perkuliahan,
            'tahun_akademik'      => $t->tahun_akademik,
            'waktu_berakhir'      => $t->waktu_berakhir?->format('Y-m-d H:i:s'),
            'detail_tagihan'      => $t->detail_tagihan,
            'total_tagihan'       => (float) $t->total_tagihan,
            'total_potongan'      => (float) $t->total_potongan,
            'nominal_ditagih'     => (float) $t->nominal_ditagih,
            'nominal_terbayar'    => (float) $t->nominal_terbayar,
            'jenis_tagihan'       => $t->jenis_tagihan,
            'status_aktif'        => $t->status_aktif,
            'status_lunas'        => $this->isLunas($t),
        ];
    }

    // ──────────────────────────────────────────────
    //  Massal & Mutasi
    // ──────────────────────────────────────────────

    /**
     * Ambil tagihan untuk banyak NPM sekaligus, optional filter tahun akademik.
     *
     * @param  string[]  $npms
     * @param  string[]  $tahunAkademik
     */
    public function getByNpms(array $npms, array $tahunAkademik = []): Collection
    {
        $query = Tagihan::with('pembayaran')->whereIn('npm', $npms);

        if (! empty($tahunAkademik)) {
            $query->whereIn('tahun_akademik', $tahunAkademik);
        }

        return $query->orderBy('npm')->orderBy('tahun_akademik', 'desc')->get();
    }

    /**
     * Update tagihan berdasarkan nomor_tagihan.
     */
    public function updateByNomor(string $nomorTagihan, array $data): ?Tagihan
    {
        $tagihan = $this->getByNomor($nomorTagihan);

        if (! $tagihan) {
            return null;
        }

        // Mapping field dari request ke kolom model
        $mapping = [
            'kode_prodi'      => 'kode_program_studi',
            'jumlah_tagihan'  => 'total_tagihan',
        ];

        $mapped = [];
        foreach ($data as $key => $value) {
            $col = $mapping[$key] ?? $key;
            $mapped[$col] = $value;
        }

        $tagihan->fill($mapped)->save();

        return $tagihan->fresh();
    }

    /**
     * Hapus (soft delete) tagihan berdasarkan nomor_tagihan.
     */
    public function deleteByNomor(string $nomorTagihan): bool
    {
        $tagihan = $this->getByNomor($nomorTagihan);

        if (! $tagihan) {
            return false;
        }

        return (bool) $tagihan->delete();
    }

    /**
     * Buat tagihan baru (general-purpose, semua jenis tagihan).
     *
     * `id_record_tagihan` dan `nomor_tagihan` adalah varchar(30) — generator
     * di bawah menjaga keduanya tetap pendek terlepas dari panjang NPM/jenis_tagihan.
     */
    public function create(array $data): Tagihan
    {
        return Tagihan::create([
            'id_record_tagihan'     => $data['id_record_tagihan'] ?? $this->generateIdRecord(),
            'nomor_tagihan'         => $data['nomor_tagihan'] ?? $this->generateNomorTagihan($data['jenis_tagihan'] ?? 'TGH'),
            'npm'                   => $data['npm'],
            'nama_mahasiswa'        => $data['nama_mahasiswa'],
            'nama_fakultas'         => $data['nama_fakultas'] ?? '',
            'kode_program_studi'    => $data['kode_program_studi'],
            'nama_program_studi'    => $data['nama_program_studi'] ?? '',
            'id_kelas_perkuliahan'  => $data['id_kelas_perkuliahan'] ?? '',
            'nama_kelas_perkuliahan'=> $data['nama_kelas_perkuliahan'] ?? null,
            'tahun_akademik'        => $data['tahun_akademik'],
            'waktu_berakhir'        => $data['waktu_berakhir'] ?? now()->addYear(),
            'detail_tagihan'        => $data['detail_tagihan'] ?? null,
            'total_tagihan'         => $data['total_tagihan'],
            'detail_potongan'       => $data['detail_potongan'] ?? null,
            'total_potongan'        => $data['total_potongan'] ?? 0,
            'nominal_ditagih'       => $data['nominal_ditagih'] ?? $data['total_tagihan'],
            'nominal_terbayar'      => $data['nominal_terbayar'] ?? 0,
            'jenis_tagihan'         => $data['jenis_tagihan'] ?? 'LAINNYA',
            'status_aktif'          => $data['status_aktif'] ?? 'Y',
            'khs'                   => $data['khs'] ?? 0,
        ]);
    }

    /**
     * `{tahun}-{5 digit acak}`, mengikuti konvensi data id_record_tagihan yang ada.
     */
    private function generateIdRecord(): string
    {
        do {
            $candidate = now()->format('Y') . '-' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        } while (Tagihan::withTrashed()->where('id_record_tagihan', $candidate)->exists());

        return $candidate;
    }

    /**
     * `{kode_jenis}/{tanggal}/{acak}` — selalu jauh di bawah batas varchar(30)
     * terlepas dari panjang jenis_tagihan yang dikirim.
     */
    private function generateNomorTagihan(string $jenisTagihan): string
    {
        $kode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $jenisTagihan) ?: 'TGH', 0, 4));

        do {
            $candidate = $kode . '/' . now()->format('Ymd') . '/' . strtoupper(Str::random(6));
        } while (Tagihan::withTrashed()->where('nomor_tagihan', $candidate)->exists());

        return $candidate;
    }

    /**
     * Buat tagihan PMB (Penerimaan Mahasiswa Baru).
     */
    public function createPMB(array $data): Tagihan
    {
        $nomorTagihan = 'PMB-' . $data['npm'] . '-' . now()->format('YmdHis') . '-' . rand(100, 999);

        $tagihan = Tagihan::create([
            'nomor_tagihan'        => $nomorTagihan,
            'npm'                  => $data['npm'],
            'nama_mahasiswa'       => $data['nama_mahasiswa'],
            'kode_program_studi'   => $data['kode_prodi'],
            'id_kelas_perkuliahan' => $data['id_kelas_perkuliahan'],
            'tahun_akademik'       => $data['tahun_akademik'],
            'total_tagihan'        => $data['jumlah_tagihan'],
            'nominal_ditagih'      => $data['jumlah_tagihan'],
            'nominal_terbayar'     => 0,
            'jenis_tagihan'        => 'PMB',
            'status_aktif'         => 'Y',
            'waktu_berakhir'       => now()->addYear(),
        ]);

        return $tagihan;
    }

    /**
     * Format data pembayaran.
     */
    public function formatPembayaran(Pembayaran $p): array
    {
        return [
            'id_record_pembayaran' => $p->id_record_pembayaran,
            'id_record_tagihan'    => $p->id_record_tagihan,
            'nomor_tagihan'        => $p->nomor_tagihan,
            'waktu_transaksi'      => $p->waktu_transaksi?->format('Y-m-d H:i:s'),
            'kanal'                => $p->kanal,
            'jumlah_pembayaran'    => (float) $p->jumlah_pembayaran,
            'from_bank'            => $p->from_bank,
            'keterangan'           => $p->keterangan,
            'proses'               => $p->proses,
        ];
    }
}
