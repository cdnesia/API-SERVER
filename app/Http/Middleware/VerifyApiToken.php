<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use App\Support\ApiResponse;
use App\Support\SnapSignature;
use Closure;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use UnexpectedValueException;

class VerifyApiToken
{
    /**
     * Verify the Bearer JWT (RS256, server keypair) to resolve the client,
     * then verify a per-request symmetric (HMAC-SHA512) signature so a
     * stolen bearer token alone isn't enough to forge requests.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $token = $request->bearerToken();

        if (! $token) {
            return ApiResponse::error('Unauthenticated.', null, 401, 401000);
        }

        try {
            $publicKey = File::get(base_path(config('jwt.public_key_path')));
            $decoded = JWT::decode($token, new Key($publicKey, config('jwt.algo')));
        } catch (ExpiredException) {
            return ApiResponse::error('Token expired.', null, 401, 401002);
        } catch (UnexpectedValueException) {
            return ApiResponse::error('Invalid token.', null, 401, 401003);
        }

        $client = ApiClient::where('client_id', $decoded->sub)->first();

        if (! $client || ! $client->is_active) {
            return ApiResponse::error('Invalid token.', null, 401, 401003);
        }

        $timestamp = $request->header('X-TIMESTAMP');
        $signature = $request->header('X-SIGNATURE');

        if (! $timestamp || ! $signature) {
            return ApiResponse::error('X-TIMESTAMP and X-SIGNATURE headers are required.', null, 400, 400002);
        }

        if (! SnapSignature::verifyTimestamp($timestamp, (int) config('jwt.timestamp_tolerance'))) {
            return ApiResponse::error('Invalid or stale X-TIMESTAMP.', null, 401, 401007);
        }

        $verified = SnapSignature::verifySymmetric(
            method: $request->method(),
            path: '/'.$request->path(),
            accessToken: $token,
            rawBody: $request->getContent(),
            timestamp: $timestamp,
            signatureBase64: $signature,
            clientSecret: $client->client_secret,
        );

        if (! $verified) {
            return ApiResponse::error('Invalid signature.', null, 401, 401010);
        }

        $request->attributes->set('api_client', $client);
        $request->attributes->set('api_scopes', (array) ($decoded->scopes ?? []));

        return $next($request);
    }
}
