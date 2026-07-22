<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mahasiswa extends Model
{
    protected $connection = 'db_siade';

    public $table = 'master_mahasiswa';

    protected $fillable = [
        'nama_mahasiswa',
        'npm',
        'va_code',
        'tahun_angkatan',
        'kode_program_studi',
        'program_kuliah_id',
        'jenis_pendaftaran_id',
        'pa_id',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'agama_id',
        'nik',
        'nisn',
        'npsn',
        'npwp',
        'no_kipk',
        'kewarganegaraan',
        'jalan',
        'dusun',
        'rt',
        'rw',
        'kelurahan',
        'kode_pos',
        'wilayah_id',
        'jenis_tinggal_id',
        'alat_transportasi_id',
        'telepon',
        'handphone',
        'email',
        'penerima_kps',
        'nomor_kps',
        'nik_ayah',
        'nama_ayah',
        'tanggal_lahir_ayah',
        'pendidikan_ayah_id',
        'pekerjaan_ayah_id',
        'penghasilan_ayah_id',
        'nik_ibu',
        'nama_ibu_kandung',
        'tanggal_lahir_ibu',
        'pendidikan_ibu_id',
        'pekerjaan_ibu_id',
        'penghasilan_ibu_id',
        'nama_wali',
        'tanggal_lahir_wali',
        'pendidikan_wali_id',
        'pekerjaan_wali_id',
        'penghasilan_wali_id',
        'kebutuhan_khusus_mahasiswa_id',
        'kebutuhan_khusus_ayah_id',
        'kebutuhan_khusus_ibu_id',
        'status',
        'isi_biodata',
    ];

    /**
     * Mahasiswa terdaftar di satu program studi.
     */
    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'kode_program_studi', 'kode_program_studi');
    }

    /**
     * Satu mahasiswa memiliki banyak KRS.
     */
    public function krs(): HasMany
    {
        return $this->hasMany(KRS::class, 'npm', 'npm');
    }
}

