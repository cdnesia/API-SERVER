<?php

namespace App\Support;

use Carbon\Carbon;

class SnapSignature
{
    /**
     * Reject requests whose `X-TIMESTAMP` is too far from server time
     * (malformed or stale/future timestamps are both rejected).
     */
    public static function verifyTimestamp(string $timestamp, int $toleranceSeconds): bool
    {
        try {
            $sent = Carbon::parse($timestamp);
        } catch (\Throwable) {
            return false;
        }

        return abs(Carbon::now()->getTimestamp() - $sent->getTimestamp()) <= $toleranceSeconds;
    }

    /**
     * Verify an asymmetric (SHA256withRSA) signature, e.g. for the B2B
     * access-token request: `stringToSign = "{clientId}|{timestamp}"`.
     */
    public static function verifyAsymmetric(string $stringToSign, string $signatureBase64, string $publicKeyPem): bool
    {
        $signature = base64_decode($signatureBase64, true);

        if ($signature === false) {
            return false;
        }

        return openssl_verify($stringToSign, $signature, $publicKeyPem, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Verify a symmetric (HMAC-SHA512) per-request signature:
     * `stringToSign = "{METHOD}:{path}:{accessToken}:{sha256(minify(body))}:{timestamp}"`.
     */
    public static function verifySymmetric(
        string $method,
        string $path,
        string $accessToken,
        string $rawBody,
        string $timestamp,
        string $signatureBase64,
        string $clientSecret,
    ): bool {
        $minifiedBody = $rawBody === '' ? '' : json_encode(json_decode($rawBody, true), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($minifiedBody === false) {
            return false;
        }

        $bodyHash = strtolower(hash('sha256', $minifiedBody));

        $stringToSign = "{$method}:{$path}:{$accessToken}:{$bodyHash}:{$timestamp}";

        $expected = base64_encode(hash_hmac('sha512', $stringToSign, $clientSecret, true));

        return hash_equals($expected, $signatureBase64);
    }
}
