<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;

class ApiResponse
{
    /**
     * Build a successful JSON response.
     */
    public static function success(
        mixed $data = null,
        string $message = '',
        int $code = 200,
        array $meta = [],
    ): JsonResponse {
        [$data, $meta] = self::extractPagination($data, $meta);

        $payload = [
            'success' => true,
            'error_code' => 0,
            'error_desc' => $message,
            'data' => $data ?? '',
        ];

        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $code);
    }

    /**
     * Build a failed JSON response.
     *
     * `$errorCode` is a separate, more granular code than the HTTP `$code`
     * (e.g. HTTP 500 with error_code 500100 for a specific failure under
     * that status). When omitted, it defaults to `$code * 1000` so callers
     * only need to supply a sub-code for cases that need one.
     */
    public static function error(
        string $message = 'Something went wrong',
        mixed $data = null,
        int $code = 400,
        ?int $errorCode = null,
    ): JsonResponse {
        $payload = [
            'success' => false,
            'error_code' => $errorCode ?? ($code * 1000),
            'error_desc' => $message,
            'data' => $data ?? '',
        ];

        return response()->json($payload, $code);
    }

    /**
     * Pull pagination metadata out of paginator/resource-collection payloads
     * so `data` stays a plain array/collection and `meta` carries the rest.
     */
    protected static function extractPagination(mixed $data, array $meta): array
    {
        if ($data instanceof ResourceCollection && $data->resource instanceof AbstractPaginator) {
            $paginated = $data->response()->getData(true);

            return [
                $paginated['data'],
                array_merge($meta, $paginated['meta'] ?? []),
            ];
        }

        if ($data instanceof AbstractPaginator) {
            return [
                $data->items(),
                array_merge($meta, [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'last_page' => $data->lastPage(),
                    'total' => $data->total(),
                ]),
            ];
        }

        return [$data, $meta];
    }
}
