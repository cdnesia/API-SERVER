<?php

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonBody::class,
        ]);

        $middleware->alias([
            'jwt.auth' => \App\Http\Middleware\VerifyApiToken::class,
            'scope'    => \App\Http\Middleware\VerifyScope::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api', 'api/*'),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api', 'api/*')) {
                return ApiResponse::error(
                    message: 'The given data was invalid.',
                    code: 422,
                    data: $e->errors(),
                );
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api', 'api/*')) {
                return ApiResponse::error('Unauthenticated.', code: 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api', 'api/*')) {
                return ApiResponse::error($e->getMessage() ?: 'This action is unauthorized.', code: 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api', 'api/*')) {
                return ApiResponse::error('Resource not found.', code: 404);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api', 'api/*')) {
                return ApiResponse::error('Endpoint not found.', code: 404);
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api', 'api/*')) {
                return ApiResponse::error('Method not allowed.', code: 405);
            }
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api', 'api/*')) {
                return ApiResponse::error('Too many requests. Please try again later.', code: 429);
            }
        });

        // ── Database Connection Errors ──────────────────────────
        $exceptions->render(function (PDOException $e, Request $request) {
            if ($request->is('api', 'api/*')) {
                return ApiResponse::error(
                    message: 'Gagal terhubung ke database. Silakan coba beberapa saat lagi.',
                    code: 503,
                    errorCode: 503001,
                );
            }
        });

        $exceptions->render(function (QueryException $e, Request $request) {
            if (! $request->is('api', 'api/*')) {
                return null;
            }

            // Deteksi error koneksi vs error query biasa
            $sqlState = $e->getPrevious()?->getCode() ?? '';
            $message  = $e->getPrevious()?->getMessage() ?? $e->getMessage();

            $connectionErrors = ['HY000', '08001', '08006', '08004', '57P01'];

            if (in_array($sqlState, $connectionErrors, true)
                || str_contains(strtolower($message), 'connection')
                || str_contains(strtolower($message), 'server has gone away')
                || str_contains(strtolower($message), 'too many connections')
            ) {
                return ApiResponse::error(
                    message: 'Gagal terhubung ke database. Silakan coba beberapa saat lagi.',
                    code: 503,
                    errorCode: 503001,
                );
            }

            return ApiResponse::error(
                message: config('app.debug')
                    ? 'Query error: ' . $e->getMessage()
                    : 'Terjadi kesalahan pada database.',
                code: 500,
                errorCode: 500001,
            );
        });

        // ── Generic fallback ──────────────────────────────────────
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api', 'api/*')) {
                return null;
            }

            $code = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            return ApiResponse::error(
                message: config('app.debug') ? $e->getMessage() : 'Server Error.',
                code: $code,
            );
        });
    })->create();
