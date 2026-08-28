<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BipotPerAngkatan extends Model
{
    protected $connection = 'db_simaku';

    public $table = 'master_bipot_per_angkatan';

    public $timestamps = false;

    protected $fillable = [
        'kode_tahun',
        'nama_tahun',
        'id_program_kuliah',
        'kode_prodi',
    ];

    protected function casts(): array
    {
        return [
            'id_program_kuliah' => 'integer',
        ];
    }

    /**
     * Rincian nominal bipot per semester untuk angkatan & prodi ini.
     */
    public function perSemester(): HasMany
    {
        return $this->hasMany(BipotPerSemester::class, 'id_bipot_angkatan', 'id');
    }
}
