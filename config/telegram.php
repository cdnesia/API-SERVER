<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Configuration
    |--------------------------------------------------------------------------
    |
    | Setel melalui .env: TELEGRAM_BOT_TOKEN, TELEGRAM_DEFAULT_CHAT_ID
    |
    */

    'bot_token'       => env('TELEGRAM_BOT_TOKEN'),

    'default_chat_id' => env('TELEGRAM_DEFAULT_CHAT_ID'),

    'api_url'         => env('TELEGRAM_API_URL', 'https://api.telegram.org'),

    /*
    |--------------------------------------------------------------------------
    | Retry & Timeout
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env('TELEGRAM_TIMEOUT', 10),

    'retry' => [
        'times'    => (int) env('TELEGRAM_RETRY_TIMES', 3),
        'sleep_ms' => (int) env('TELEGRAM_RETRY_SLEEP_MS', 500),
    ],

];
