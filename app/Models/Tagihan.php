<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tagihan extends Model
{
    use SoftDeletes;

    protected $connection = 'db_payment';

    public $table = 'tagihan';

    protected $fillable = [
        'id_record_tagihan',
        'nomor_tagihan',
        'npm',
        'nama_mahasiswa',
        'nama_fakultas',
        'kode_program_studi',
        'nama_program_studi',
        'id_kelas_perkuliahan',
        'nama_kelas_perkuliahan',
        'tahun_akademik',
        'waktu_berakhir',
        'detail_tagihan',
        'total_tagihan',
        'detail_potongan',
        'total_potongan',
        'nominal_ditagih',
        'nominal_terbayar',
        'jenis_tagihan',
        'status_aktif',
        'khs',
    ];

    protected function casts(): array
    {
        return [
            'detail_tagihan'  => 'array',
            'detail_potongan' => 'array',
            'total_tagihan'   => 'decimal:2',
            'total_potongan'  => 'decimal:2',
            'nominal_ditagih' => 'decimal:2',
            'nominal_terbayar'=> 'decimal:2',
            'waktu_berakhir'  => 'datetime',
            'status_aktif'    => 'string',
            'khs'             => 'integer',
        ];
    }

    /**
     * Satu tagihan memiliki banyak pembayaran.
     */
    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class, 'id_record_tagihan', 'id_record_tagihan');
    }
}
