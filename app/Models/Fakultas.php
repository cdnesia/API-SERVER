<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fakultas extends Model
{
    protected $connection = 'db_siade';

    public $table = 'master_fakultas';

    protected $fillable = [
        'kode_fakultas',
        'nama_fakultas_idn',
        'nama_fakultas_eng',
        'dekan_id',
        'status',
    ];

    /**
     * Satu fakultas memiliki banyak program studi.
     */
    public function programStudi(): HasMany
    {
        return $this->hasMany(ProgramStudi::class, 'fakultas_id', 'id');
    }
}
