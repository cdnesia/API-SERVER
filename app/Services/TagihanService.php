<?php

namespace App\Services;

use App\Models\Pembayaran;
use App\Models\Tagihan;
use Illuminate\Support\Collection;

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
