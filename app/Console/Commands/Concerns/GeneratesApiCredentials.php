<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Str;

trait GeneratesApiCredentials
{
    /**
     * @return array{private_key: string, public_key: string, client_secret: string}
     */
    protected function generateApiCredentials(): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($resource, $privateKey);
        $publicKey = openssl_pkey_get_details($resource)['key'];

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
            'client_secret' => Str::random(64),
        ];
    }
}
