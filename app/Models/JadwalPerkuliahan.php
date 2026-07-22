<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JadwalPerkuliahan extends Model
{
    protected $connection = 'db_siade';

    public $table = 'tbl_jadwal_perkuliahan';

    protected $fillable = [
        'kode_program_studi',
        'tahun_akademik',
        'program_kuliah_id',
        'hari_id',
        'ruang_id',
        'dosen_id',
        'kelompok',
        'mata_kuliah_id',
        'jam_mulai',
        'jam_selesai',
        'status',
    ];

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(KurikulumMatakuliah::class, 'mata_kuliah_id', 'id');
    }

    public function hari(): BelongsTo
    {
        return $this->belongsTo(Hari::class, 'hari_id', 'id');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dosen_id', 'id');
    }

    public function krs(): HasMany
    {
        return $this->hasMany(KRS::class, 'jadwal_id', 'id');
    }
}
