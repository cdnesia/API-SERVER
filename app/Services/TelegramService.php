<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    /**
     * Kirim pesan teks ke Telegram.
     *
     * @param  array|null  $replyMarkup  e.g. ['inline_keyboard' => [[['text' => 'OK', 'callback_data' => 'ok']]]]
     *
     * @throws ConnectionException
     */
    public function sendMessage(
        string $text,
        ?string $chatId = null,
        string $parseMode = 'HTML',
        ?array $replyMarkup = null,
    ): array {
        $payload = [
            'chat_id'    => $chatId ?? config('telegram.default_chat_id'),
            'text'       => $text,
            'parse_mode' => $parseMode,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        return $this->call('sendMessage', $payload);
    }

    /**
     * Kirim foto ke Telegram (via URL atau path lokal).
     */
    public function sendPhoto(
        string $photo,
        ?string $caption = null,
        ?string $chatId = null,
    ): array {
        $payload = [
            'chat_id' => $chatId ?? config('telegram.default_chat_id'),
            'photo'   => $photo,
        ];

        if ($caption) {
            $payload['caption'] = $caption;
        }

        return $this->call('sendPhoto', $payload);
    }

    /**
     * Kirim dokumen ke Telegram.
     */
    public function sendDocument(
        string $document,
        ?string $caption = null,
        ?string $chatId = null,
    ): array {
        $payload = [
            'chat_id'  => $chatId ?? config('telegram.default_chat_id'),
            'document' => $document,
        ];

        if ($caption) {
            $payload['caption'] = $caption;
        }

        return $this->call('sendDocument', $payload);
    }

    /**
     * Broadcast pesan ke multiple chat IDs.
     *
     * @param  string[]  $chatIds
     * @return array{success: array, failed: array}
     */
    public function broadcast(string $text, array $chatIds): array
    {
        $success = [];
        $failed  = [];

        foreach ($chatIds as $id) {
            try {
                $result    = $this->sendMessage($text, $id);
                $success[] = ['chat_id' => $id, 'message_id' => $result['result']['message_id'] ?? null];
            } catch (\Throwable $e) {
                $failed[] = ['chat_id' => $id, 'error' => $e->getMessage()];
            }
        }

        return compact('success', 'failed');
    }

    // ──────────────────────────────────────────────
    //  Internal
    // ──────────────────────────────────────────────

    /**
     * Panggil Telegram Bot API method.
     *
     * @throws \RuntimeException  bila API mengembalikan error.
     * @throws ConnectionException
     */
    protected function call(string $method, array $params): array
    {
        $token  = config('telegram.bot_token');
        $apiUrl = config('telegram.api_url');

        if (! $token) {
            throw new \RuntimeException('TELEGRAM_BOT_TOKEN belum disetel di .env.');
        }

        $response = Http::timeout(config('telegram.timeout'))
            ->retry(
                config('telegram.retry.times'),
                config('telegram.retry.sleep_ms'),
            )
            ->post("{$apiUrl}/bot{$token}/{$method}", $params);

        $body = $response->json();

        if (! ($body['ok'] ?? false)) {
            $desc = $body['description'] ?? 'Unknown error';
            Log::error("[Telegram] API error: {$desc}", compact('method', 'params', 'body'));

            throw new \RuntimeException("Telegram API error: {$desc}");
        }

        return $body;
    }
}
