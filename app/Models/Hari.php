<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hari extends Model
{
    protected $connection = 'db_siade';

    public $table = 'master_hari';

    protected $fillable = ['nama_hari'];

    public function jadwal(): HasMany
    {
        return $this->hasMany(JadwalPerkuliahan::class, 'hari_id', 'id');
    }
}
