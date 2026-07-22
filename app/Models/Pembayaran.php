<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pembayaran extends Model
{
    protected $connection = 'db_payment';

    public $table = 'pembayaran';

    public $timestamps = false;

    protected $fillable = [
        'id_record_pembayaran',
        'id_record_tagihan',
        'waktu_transaksi',
        'waktu_transaksi_bank',
        'nomor_tagihan',
        'kanal',
        'kode_terminal',
        'jumlah_pembayaran',
        'bill_reff',
        'from_bank',
        'keterangan',
        'proses',
    ];

    protected function casts(): array
    {
        return [
            'waktu_transaksi'      => 'datetime',
            'waktu_transaksi_bank' => 'datetime',
            'jumlah_pembayaran'    => 'decimal:2',
        ];
    }

    /**
     * Pembayaran dimiliki oleh satu tagihan.
     */
    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class, 'id_record_tagihan', 'id_record_tagihan');
    }
}
