<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'client_id', 'public_key', 'client_secret', 'is_active', 'scopes'])]
#[Hidden(['client_secret'])]
class ApiClient extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'client_secret' => 'encrypted',
            'scopes' => 'array',
        ];
    }

    /**
     * Cek apakah client memiliki scope tertentu.
     * Scope `*` atau `all` berarti akses penuh.
     */
    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];

        return in_array('*', $scopes, true)
            || in_array('all', $scopes, true)
            || in_array($scope, $scopes, true);
    }

    /**
     * Cek apakah client memiliki setidaknya salah satu dari daftar scope.
     */
    public function hasAnyScope(array $scopes): bool
    {
        foreach ($scopes as $scope) {
            if ($this->hasScope($scope)) {
                return true;
            }
        }

        return false;
    }
}
