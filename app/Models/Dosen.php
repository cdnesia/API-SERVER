<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dosen extends Model
{
    protected $connection = 'db_siade_old';

    public $table = 'pegawai';

    protected $fillable = [
        'nik',
        'nama_lengkap',
        'nidn',
        'email_instansi',
        'email_pribadi',
        'hp',
        'telepon',
        'id_jenis_kelamin',
        'status_aktif',
    ];

    protected function casts(): array
    {
        return [
            'mulai_bekerja' => 'date',
            'tanggal_lahir' => 'date',
            'status_aktif'  => 'integer',
        ];
    }

    public function jadwal(): HasMany
    {
        return $this->hasMany(JadwalPerkuliahan::class, 'dosen_id', 'id');
    }
}
