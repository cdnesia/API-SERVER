<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use App\Support\SnapSignature;
use Closure;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use UnexpectedValueException;

class VerifyApiToken
{
    /** Cached public key — avoids disk I/O on every request under Octane. */
    private static ?Key $cachedPublicKey = null;

    public function handle(Request $request, Closure $next): mixed
    {
        $token = $request->bearerToken();

        if (! $token) {
            return ApiResponse::error('Unauthenticated.', null, 401, ErrorCode::UNAUTHENTICATED);
        }

        try {
            $publicKey = $this->getPublicKey();
            $decoded = JWT::decode($token, $publicKey);
        } catch (ExpiredException) {
            return ApiResponse::error('Token expired.', null, 401, ErrorCode::TOKEN_EXPIRED);
        } catch (UnexpectedValueException) {
            return ApiResponse::error('Invalid token.', null, 401, ErrorCode::INVALID_TOKEN);
        }

        $client = ApiClient::where('client_id', $decoded->sub)->first();

        if (! $client || ! $client->is_active) {
            return ApiResponse::error('Invalid token.', null, 401, ErrorCode::INVALID_TOKEN);
        }

        $timestamp = $request->header('X-TIMESTAMP');
        $signature = $request->header('X-SIGNATURE');

        if (! $timestamp || ! $signature) {
            return ApiResponse::error('X-TIMESTAMP and X-SIGNATURE headers are required.', null, 400, ErrorCode::MISSING_SIGN_HEADERS);
        }

        if (! SnapSignature::verifyTimestamp($timestamp, (int) config('jwt.timestamp_tolerance'))) {
            return ApiResponse::error('Invalid or stale X-TIMESTAMP.', null, 401, ErrorCode::STALE_TIMESTAMP);
        }

        try {
            $clientSecret = $client->client_secret;
        } catch (DecryptException) {
            return ApiResponse::error('Invalid token.', null, 401, ErrorCode::INVALID_TOKEN);
        }

        $verified = SnapSignature::verifySymmetric(
            method: $request->method(),
            path: '/'.$request->path(),
            accessToken: $token,
            rawBody: $request->getContent(),
            timestamp: $timestamp,
            signatureBase64: $signature,
            clientSecret: $clientSecret,
        );

        if (! $verified) {
            return ApiResponse::error('Invalid signature.', null, 401, ErrorCode::INVALID_SYM_SIGNATURE);
        }

        $request->attributes->set('api_client', $client);
        $request->attributes->set('api_scopes', (array) ($decoded->scopes ?? []));

        return $next($request);
    }

    /**
     * Return the JWT public key, cached in-memory for Octane.
     */
    private function getPublicKey(): Key
    {
        if (self::$cachedPublicKey === null) {
            $contents = File::get(base_path(config('jwt.public_key_path')));
            self::$cachedPublicKey = new Key($contents, config('jwt.algo'));
        }

        return self::$cachedPublicKey;
    }
}
