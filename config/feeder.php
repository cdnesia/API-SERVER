<?php

return [

    /*
    |--------------------------------------------------------------------------
    | NeoFeeder API Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk menghubungkan aplikasi dengan API NeoFeeder.
    | URL, username, dan password digunakan untuk autentikasi GetToken
    | sebelum melakukan request data lainnya.
    |
    */

    'url' => env('NEOFEEDER_URL', 'https://api.neofeeder.example.com'),

    'username' => env('NEOFEEDER_USERNAME', ''),

    'password' => env('NEOFEEDER_PASSWORD', ''),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Options
    |--------------------------------------------------------------------------
    |
    | `timeout` adalah batas waktu maksimal (dalam detik) untuk setiap
    | request HTTP ke API NeoFeeder.
    |
    | `retry` mengatur jumlah percobaan ulang dan jeda antar percobaan
    | jika request gagal.
    |
    */

    'timeout' => (int) env('NEOFEEDER_TIMEOUT', 30),

    'retry' => [
        'times' => (int) env('NEOFEEDER_RETRY_TIMES', 2),
        'sleep' => (int) env('NEOFEEDER_RETRY_SLEEP', 500),
    ],

];
