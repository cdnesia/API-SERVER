<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramStudi extends Model
{
    protected $connection = 'db_siade';

    public $table = 'master_program_studi';

    protected $fillable = [
        'kode_program_studi',
        'nama_program_studi_idn',
        'nama_program_studi_eng',
        'jenjang',
        'akreditasi',
        'status',
        'fakultas_id',
        'kaprodi_id',
        'program_perkuliahan_id',
    ];

    protected function casts(): array
    {
        return [
            'program_perkuliahan_id' => 'array',
        ];
    }

    /**
     * Prodi dimiliki oleh satu fakultas.
     */
    public function fakultas(): BelongsTo
    {
        return $this->belongsTo(Fakultas::class, 'fakultas_id', 'id');
    }

    /**
     * Satu prodi memiliki banyak mahasiswa.
     */
    public function mahasiswa(): HasMany
    {
        return $this->hasMany(Mahasiswa::class, 'kode_program_studi', 'kode_program_studi');
    }
}
