<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KurikulumMatakuliah extends Model
{
    protected $connection = 'db_siade';

    public $table = 'master_kurikulum_matakuliah';

    protected $fillable = [
        'kurikulum_id',
        'kode_program_studi',
        'kode_mata_kuliah',
        'nama_mata_kuliah_idn',
        'nama_mata_kuliah_eng',
        'mata_kuliah_tipe',
        'sks_tatap_muka',
        'sks_praktek',
        'sks_prak_lap',
        'sks_simulasi',
        'sks_mata_kuliah',
        'semester',
        'min_pertemuan',
        'max_pertemuan',
        'jenis_matakuliah_id',
        'is_mbkm',
        'is_mk_universitas',
        'status',
        'prasyarat_lulus',
    ];

    protected function casts(): array
    {
        return [
            'is_mbkm'           => 'boolean',
            'is_mk_universitas' => 'boolean',
            'prasyarat_lulus'   => 'array',
        ];
    }

    public function krs(): HasMany
    {
        return $this->hasMany(KRS::class, 'mata_kuliah_id', 'id');
    }
}
