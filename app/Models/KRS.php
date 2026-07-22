<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KRS extends Model
{
    protected $connection = 'db_siade';

    public $table = 'tbl_mahasiswa_krs';

    protected $fillable = [
        'jadwal_id',
        'mata_kuliah_id',
        'npm',
        'kode_tahun_akademik',
        'nilai_angka',
        'nilai_huruf',
        'nilai_bobot',
        'nilai_mutu',
        'persetujuan_pa',
        'datetime_persetujuan_pa',
        'lulus',
        'cek_edome',
    ];

    /**
     * KRS milik satu mahasiswa.
     */
    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'npm', 'npm');
    }

    /**
     * KRS terhubung ke jadwal perkuliahan.
     */
    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(JadwalPerkuliahan::class, 'jadwal_id', 'id');
    }

    /**
     * Akses mata kuliah melalui jadwal (mayoritas data).
     * Gunakan: KRS::with('jadwal.mataKuliah')->get()
     */
    public function mataKuliahViaJadwal(): ?KurikulumMatakuliah
    {
        return $this->jadwal?->mataKuliah;
    }

    /**
     * KRS merujuk langsung ke mata kuliah (minoritas data, mata_kuliah_id tidak null).
     */
    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(KurikulumMatakuliah::class, 'mata_kuliah_id', 'id');
    }
}

