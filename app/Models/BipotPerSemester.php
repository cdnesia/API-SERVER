<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BipotPerSemester extends Model
{
    protected $connection = 'db_simaku';

    public $table = 'master_bipot_per_semester';

    public $timestamps = false;

    protected $fillable = [
        'id_bipot_angkatan',
        'id_bipot',
        'nominal',
        'semester',
        'status_awal',
        'status_mahasiswa',
    ];

    protected function casts(): array
    {
        return [
            'id_bipot_angkatan' => 'integer',
            'id_bipot'          => 'integer',
            'nominal'           => 'decimal:0',
            'semester'          => 'integer',
            'status_awal'       => 'array',
            'status_mahasiswa'  => 'array',
        ];
    }

    public function angkatan(): BelongsTo
    {
        return $this->belongsTo(BipotPerAngkatan::class, 'id_bipot_angkatan', 'id');
    }

    public function bipot(): BelongsTo
    {
        return $this->belongsTo(MasterBipot::class, 'id_bipot', 'id');
    }
}
