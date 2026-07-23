<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

/**
 * Middleware untuk memeriksa apakah API client memiliki scope yang dibutuhkan.
 *
 * Cara pakai di route:
 *   Route::middleware('scope:tagihan:read')->group(...)
 *   Route::middleware('scope:khs:read,krs:read')->group(...)   // butuh salah satu
 *
 * Scope `*` atau `all` pada client berarti akses penuh ke semua endpoint.
 */
class VerifyScope
{
    /**
     * Handle an incoming request.
     *
     * @param  string  ...$scopes  Daftar scope yang dibutuhkan (dipisah koma di route definition)
     */
    public function handle(Request $request, Closure $next, string ...$scopes): mixed
    {
        $clientScopes = (array) $request->attributes->get('api_scopes', []);

        // Jika client punya wildcard scope, izinkan semua
        if (in_array('*', $clientScopes, true) || in_array('all', $clientScopes, true)) {
            return $next($request);
        }

        // Jika tidak ada scope yang dibutuhkan, izinkan (no restriction)
        if (empty($scopes)) {
            return $next($request);
        }

        // Cek apakah client punya setidaknya salah satu scope yang dibutuhkan
        $hasScope = false;
        foreach ($scopes as $required) {
            if (in_array($required, $clientScopes, true)) {
                $hasScope = true;
                break;
            }
        }

        if (! $hasScope) {
            return ApiResponse::error(
                'Forbidden. Required scope(s): ' . implode(', ', $scopes),
                null,
                403,
                403001
            );
        }

        return $next($request);
    }
}
