<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Support\ApiResponse;
use App\Support\SnapSignature;
use Firebase\JWT\JWT;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TokenController extends Controller
{
    /**
     * Issue an access token for a client that proves possession of its
     * private key via an asymmetric (SNAP-style) request signature.
     *
     * Endpoint:  POST /api/oauth/token
     * Headers:   X-TIMESTAMP, X-CLIENT-KEY, X-SIGNATURE
     * Body (JSON): { "grantType": "client_credentials" }
     */
    public function issue(Request $request): JsonResponse
    {
        $timestamp = $request->header('X-TIMESTAMP');
        $clientKey = $request->header('X-CLIENT-KEY');
        $signature = $request->header('X-SIGNATURE');

        if (! $timestamp || ! $clientKey || ! $signature) {
            return ApiResponse::error('X-TIMESTAMP, X-CLIENT-KEY, and X-SIGNATURE headers are required.', null, 400, 400001);
        }

        $validator = Validator::make($request->json()->all(), [
            'grantType' => ['required', 'string', 'in:client_credentials'],
        ], [
            'grantType.required' => 'grantType wajib diisi.',
            'grantType.in'       => 'grantType harus bernilai client_credentials.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }

        if (! SnapSignature::verifyTimestamp($timestamp, (int) config('jwt.timestamp_tolerance'))) {
            return ApiResponse::error('Invalid or stale X-TIMESTAMP.', null, 401, 401007);
        }

        $client = ApiClient::where('client_id', $clientKey)->first();

        if (! $client || ! $client->is_active) {
            return ApiResponse::error('Invalid client.', null, 401, 401001);
        }

        $stringToSign = "{$clientKey}|{$timestamp}";

        if (! SnapSignature::verifyAsymmetric($stringToSign, $signature, $client->public_key)) {
            return ApiResponse::error('Invalid signature.', null, 401, 401008);
        }

        $client->forceFill(['last_used_at' => now()])->save();

        $ttl = (int) config('jwt.ttl');
        $now = time();

        $payload = [
            'iss' => config('jwt.issuer'),
            'sub' => $client->client_id,
            'iat' => $now,
            'exp' => $now + $ttl,
            'jti' => (string) Str::uuid(),
        ];

        $privateKey = File::get(base_path(config('jwt.private_key_path')));
        $token = JWT::encode($payload, $privateKey, config('jwt.algo'));

        return ApiResponse::success([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => $ttl,
        ]);
    }
}
