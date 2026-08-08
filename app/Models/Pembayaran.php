<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pembayaran extends Model
{
    protected $connection = 'db_simaku';

    public $table = 'tbl_pembayaran_mahasiswa';

    public $timestamps = false;

    protected $fillable = [
        'id_record_tagihan',
        'id_record_pembayaran',
        'tahun_akademik',
        'pmb',
        'npm',
        'id_bipot',
        'nama_bipot',
        'nominal',
        'waktu_transaksi',
        'bank',
        'metode',
    ];

    protected function casts(): array
    {
        return [
            'waktu_transaksi' => 'datetime',
            'nominal'         => 'decimal:2',
        ];
    }

    /**
     * Relasi ke tagihan (cross-database — Tagihan ada di db_payment).
     *
     * ⚠️ Hubungan lintas database ini tidak bisa di-eager-load; gunakan
     *    query manual bila perlu join.
     */
    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class, 'id_record_tagihan', 'id_record_tagihan')
            ->withDefault();
    }
}
