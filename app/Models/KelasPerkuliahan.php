<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KelasPerkuliahan extends Model
{
    protected $connection = 'db_siade';

    public $table = 'master_kelas_perkuliahan';

    protected $fillable = [
        'nama_program_perkuliahan',
    ];
}
