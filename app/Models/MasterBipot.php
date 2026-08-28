<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterBipot extends Model
{
    protected $connection = 'db_simaku';

    public $table = 'master_bipot';

    public $timestamps = false;

    protected $fillable = [
        'nama_bipot',
        'trxid',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'trxid'  => 'integer',
            'urutan' => 'integer',
        ];
    }

    /**
     * Rincian nominal per semester untuk jenis biaya (bipot) ini.
     */
    public function perSemester(): HasMany
    {
        return $this->hasMany(BipotPerSemester::class, 'id_bipot', 'id');
    }
}
