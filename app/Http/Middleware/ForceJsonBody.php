<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class ForceJsonBody
{
    /**
     * Hanya izinkan request dengan Content-Type: application/json.
     *
     * Method yang tidak punya body (GET, HEAD, OPTIONS) dilewati.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if ($this->hasBody($request)) {
            $contentType = $request->header('Content-Type', '');

            $isJson = str_contains($contentType, 'application/json')
                || str_contains($contentType, '+json');

            if (! $isJson) {
                return ApiResponse::error(
                    'Hanya menerima body JSON. Set Content-Type: application/json.',
                    null,
                    415,
                );
            }

            if (empty(trim($request->getContent()))) {
                return ApiResponse::error(
                    'Body JSON tidak boleh kosong.',
                    null,
                    400,
                );
            }

            // Pastikan body bisa di-decode sebagai JSON
            $decoded = json_decode($request->getContent(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return ApiResponse::error(
                    'Body harus berupa JSON yang valid.',
                    ['json_error' => json_last_error_msg()],
                    400,
                );
            }
        }

        return $next($request);
    }

    /**
     * HTTP method yang lazim membawa request body.
     */
    protected function hasBody(Request $request): bool
    {
        return in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }
}
