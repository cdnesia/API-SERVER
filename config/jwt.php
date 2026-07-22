<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Asymmetric Key Pair
    |--------------------------------------------------------------------------
    |
    | RSA private key signs access tokens (RS256); the public key verifies
    | them. Paths are relative to the application base path.
    |
    */

    'private_key_path' => env('JWT_PRIVATE_KEY_PATH', 'storage/app/jwt/private.pem'),

    'public_key_path' => env('JWT_PUBLIC_KEY_PATH', 'storage/app/jwt/public.pem'),

    'algo' => env('JWT_ALGO', 'RS256'),

    'ttl' => (int) env('JWT_TTL', 900),

    'issuer' => env('JWT_ISSUER', env('APP_URL', 'http://localhost')),

    /*
    |--------------------------------------------------------------------------
    | SNAP-style Request Signing
    |--------------------------------------------------------------------------
    |
    | `timestamp_tolerance` is the max allowed skew (seconds) between a
    | request's X-TIMESTAMP and server time, for both the asymmetric token
    | request and every symmetrically-signed CRUD request.
    |
    */

    'timestamp_tolerance' => (int) env('SNAP_TIMESTAMP_TOLERANCE', 300),

];
