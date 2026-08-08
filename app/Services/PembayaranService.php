<?php

namespace App\Services;

use App\Models\Pembayaran;
use Illuminate\Support\Collection;

class PembayaranService
{
    /**
     * Ambil semua pembayaran berdasarkan NPM (melalui nomor_tagihan).
     */
    public function getByNpm(string $npm): Collection
    {
        return Pembayaran::where('nomor_tagihan', 'like', $npm . '%')
            ->orderBy('waktu_transaksi', 'desc')
            ->get();
    }

    /**
     * Ambil pembayaran berdasarkan id_record_pembayaran.
     */
    public function getByIdRecord(string $idRecord): ?Pembayaran
    {
        return Pembayaran::where('id_record_pembayaran', $idRecord)->first();
    }

    /**
     * Ambil pembayaran berdasarkan id_record_tagihan.
     */
    public function getByIdRecordTagihan(string $idRecordTagihan): Collection
    {
        return Pembayaran::where('id_record_tagihan', $idRecordTagihan)
            ->orderBy('waktu_transaksi', 'desc')
            ->get();
    }

    /**
     * Ambil pembayaran berdasarkan nomor_tagihan.
     */
    public function getByNomorTagihan(string $nomorTagihan): Collection
    {
        return Pembayaran::where('nomor_tagihan', $nomorTagihan)
            ->orderBy('waktu_transaksi', 'desc')
            ->get();
    }

    /**
     * Ambil pembayaran dalam rentang tanggal.
     */
    public function getByDateRange(string $startDate, string $endDate): Collection
    {
        return Pembayaran::whereBetween('waktu_transaksi', [$startDate, $endDate])
            ->orderBy('waktu_transaksi', 'desc')
            ->get();
    }

    /**
     * Ringkasan pembayaran per NPM.
     */
    public function getSummaryByNpm(string $npm): array
    {
        $pembayaran = $this->getByNpm($npm);

        $totalPembayaran = 0;
        foreach ($pembayaran as $p) {
            $totalPembayaran += (float) $p->jumlah_pembayaran;
        }

        return [
            'npm'                 => $npm,
            'total_pembayaran'    => number_format($totalPembayaran, 2, '.', ''),
            'jumlah_transaksi'    => $pembayaran->count(),
            'pembayaran_terakhir' => $pembayaran->first()?->waktu_transaksi?->format('Y-m-d H:i:s'),
        ];
    }

    // ──────────────────────────────────────────────
    //  Format
    // ──────────────────────────────────────────────

    /**
     * Format standar data pembayaran.
     */
    public function formatPembayaran(Pembayaran $p): array
    {
        return [
            'id_record_pembayaran' => $p->id_record_pembayaran,
            'id_record_tagihan'    => $p->id_record_tagihan,
            'nomor_tagihan'        => $p->nomor_tagihan,
            'waktu_transaksi'      => $p->waktu_transaksi?->format('Y-m-d H:i:s'),
            'waktu_transaksi_bank' => $p->waktu_transaksi_bank?->format('Y-m-d H:i:s'),
            'kanal'                => $p->kanal,
            'kode_terminal'        => $p->kode_terminal,
            'jumlah_pembayaran'    => (float) $p->jumlah_pembayaran,
            'bill_reff'            => $p->bill_reff,
            'from_bank'            => $p->from_bank,
            'keterangan'           => $p->keterangan,
            'proses'               => $p->proses,
        ];
    }
}
