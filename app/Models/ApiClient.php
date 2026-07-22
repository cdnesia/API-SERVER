<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'client_id', 'public_key', 'client_secret', 'is_active'])]
#[Hidden(['client_secret'])]
class ApiClient extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'client_secret' => 'encrypted',
        ];
    }
}
