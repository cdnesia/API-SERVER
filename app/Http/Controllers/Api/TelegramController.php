<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TelegramService;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TelegramController extends Controller
{
    public function __construct(
        protected TelegramService $telegramService,
    ) {}

    public function sendMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'text'                    => ['required', 'string', 'max:4096'],
            'chat_id'                 => ['nullable', 'string', 'max:100'],
            'parse_mode'              => ['nullable', 'string', 'in:HTML,Markdown'],
            'inline_keyboard'         => ['nullable', 'array'],
            'inline_keyboard.*'       => ['array'],
            'inline_keyboard.*.*.text' => ['required_with:inline_keyboard', 'string'],
        ], [
            'text.required'                       => 'text wajib diisi.',
            'text.max'                            => 'text maksimal :max karakter.',
            'parse_mode.in'                       => 'parse_mode harus HTML atau Markdown.',
            'inline_keyboard.*.*.text.required_with' => 'Setiap tombol harus memiliki key "text".',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        try {
            $replyMarkup = null;
            $keyboard    = $request->json('inline_keyboard');

            if ($keyboard) {
                $replyMarkup = ['inline_keyboard' => $keyboard];
            }

            $result = $this->telegramService->sendMessage(
                text: $request->json('text'),
                chatId: $request->json('chat_id'),
                parseMode: $request->json('parse_mode', 'HTML'),
                replyMarkup: $replyMarkup,
            );

            return ApiResponse::success([
                'message_id' => $result['result']['message_id'] ?? null,
                'chat'       => $result['result']['chat'] ?? null,
            ], 'Pesan berhasil dikirim.');
        } catch (\Throwable $e) {
            Log::error('TelegramController: gagal mengirim pesan.', ['message' => $e->getMessage()]);

            return ApiResponse::error(
                'Gagal mengirim pesan: ' . $e->getMessage(),
                null,
                500,
                ErrorCode::INTERNAL_ERROR,
            );
        }
    }

    public function sendPhoto(Request $request): JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'photo'    => ['required', 'string'],
            'caption'  => ['nullable', 'string', 'max:1024'],
            'chat_id'  => ['nullable', 'string', 'max:100'],
        ], [
            'photo.required' => 'photo wajib diisi (URL atau file_id Telegram).',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        try {
            $result = $this->telegramService->sendPhoto(
                photo: $request->json('photo'),
                caption: $request->json('caption'),
                chatId: $request->json('chat_id'),
            );

            return ApiResponse::success([
                'message_id' => $result['result']['message_id'] ?? null,
                'chat'       => $result['result']['chat'] ?? null,
            ], 'Foto berhasil dikirim.');
        } catch (\Throwable $e) {
            Log::error('TelegramController: gagal mengirim foto.', ['message' => $e->getMessage()]);

            return ApiResponse::error(
                'Gagal mengirim foto: ' . $e->getMessage(),
                null,
                500,
                ErrorCode::INTERNAL_ERROR,
            );
        }
    }

    public function sendDocument(Request $request): JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'document' => ['required', 'string'],
            'caption'  => ['nullable', 'string', 'max:1024'],
            'chat_id'  => ['nullable', 'string', 'max:100'],
        ], [
            'document.required' => 'document wajib diisi (URL atau file_id Telegram).',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        try {
            $result = $this->telegramService->sendDocument(
                document: $request->json('document'),
                caption: $request->json('caption'),
                chatId: $request->json('chat_id'),
            );

            return ApiResponse::success([
                'message_id' => $result['result']['message_id'] ?? null,
                'chat'       => $result['result']['chat'] ?? null,
            ], 'Dokumen berhasil dikirim.');
        } catch (\Throwable $e) {
            Log::error('TelegramController: gagal mengirim dokumen.', ['message' => $e->getMessage()]);

            return ApiResponse::error(
                'Gagal mengirim dokumen: ' . $e->getMessage(),
                null,
                500,
                ErrorCode::INTERNAL_ERROR,
            );
        }
    }

    public function broadcast(Request $request): JsonResponse
    {
        $validator = Validator::make($request->json()->all(), [
            'text'     => ['required', 'string', 'max:4096'],
            'chat_ids' => ['required', 'array', 'min:1', 'max:100'],
            'chat_ids.*' => ['string', 'max:100'],
        ], [
            'text.required'       => 'text wajib diisi.',
            'chat_ids.required'   => 'chat_ids wajib diisi.',
            'chat_ids.min'        => 'Minimal 1 chat_id.',
            'chat_ids.max'        => 'Maksimal 100 chat_id per request.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422, ErrorCode::VALIDATION_FAILED);
        }

        try {
            $result = $this->telegramService->broadcast(
                text: $request->json('text'),
                chatIds: $request->json('chat_ids'),
            );

            return ApiResponse::success($result, 'Broadcast selesai.');
        } catch (\Throwable $e) {
            Log::error('TelegramController: gagal broadcast.', ['message' => $e->getMessage()]);

            return ApiResponse::error(
                'Gagal broadcast: ' . $e->getMessage(),
                null,
                500,
                ErrorCode::INTERNAL_ERROR,
            );
        }
    }
}
