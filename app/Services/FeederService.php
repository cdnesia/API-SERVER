<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;

class FeederService
{
    private string $api_url;
    private string $username;
    private string $password;
    private int $timeout;
    private int $retryTimes;
    private int $retrySleep;

    private const CACHE_KEY = 'feeder:token';

    public function __construct()
    {
        $this->username   = config('feeder.username');
        $this->password   = config('feeder.password');
        $this->api_url    = config('feeder.url');
        $this->timeout    = config('feeder.timeout', 30);
        $this->retryTimes = config('feeder.retry.times', 2);
        $this->retrySleep = config('feeder.retry.sleep', 500);
    }

    /**
     * Get authentication token — cached via Laravel Cache (Octane-safe).
     * Automatically refreshes when expired. Returns null only after a real failure.
     */
    private function getToken(): ?string
    {
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached !== null) {
            return $cached;
        }

        return $this->refreshToken();
    }

    /**
     * Force-fetch a new token from Neofeeder and cache it.
     */
    private function refreshToken(): ?string
    {
        Cache::forget(self::CACHE_KEY);

        try {
            /** @var Response $response */
            $response = Http::asJson()
                ->timeout($this->timeout)
                ->post($this->api_url, [
                    'act' => 'GetToken',
                    'username' => $this->username,
                    'password' => $this->password,
                ]);

            if ($response->successful()) {
                $body = $response->json();

                if (isset($body['error_code']) && $body['error_code'] === 0) {
                    $token = $body['data']['token'] ?? null;
                    if ($token) {
                        $ttl = max($this->extractExpiry($token) - time(), 60);
                        Cache::put(self::CACHE_KEY, $token, $ttl);
                        return $token;
                    }
                }

                Log::error('NeofeederService: Gagal mendapatkan token.', ['response' => $body]);
            } else {
                Log::error('NeofeederService: HTTP request gagal.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('NeofeederService: Exception saat request token.', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return null;
    }

    /**
     * Check if an API response indicates the token is expired/invalid.
     */
    private function isTokenExpiredResponse(array $response): bool
    {
        $errorCode = $response['error_code'] ?? null;
        $errorDesc = strtolower($response['error_desc'] ?? '');

        return $errorCode == 401
            || str_contains($errorDesc, 'token')
            || str_contains($errorDesc, 'expired')
            || str_contains($errorDesc, 'unauthorized');
    }

    /**
     * Extract expiry timestamp from JWT (exp claim) minus 60s buffer.
     */
    private function extractExpiry(string $token): int
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return time() + 900; // fallback: 15 min
            }

            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

            return ($payload['exp'] ?? (time() + 900)) - 60;
        } catch (\Throwable) {
            return time() + 900;
        }
    }

    /**
     * Get data from Neofeeder API.
     * Automatically retries once if the token is expired/invalid.
     *
     * @param array $data
     * @return array
     */
    public function getData(array $data)
    {
        $token = $this->getToken();

        if (!$token) {
            return [
                'error_code' => 1,
                'error_desc' => 'Gagal mendapatkan token dari Neofeeder.',
                'data' => null
            ];
        }

        return $this->postDataWithRetry($data);
    }

    /**
     * POST to Neofeeder API with automatic token refresh on expiry.
     */
    private function postDataWithRetry(array $data, bool $retried = false): array
    {
        $token = $this->getToken();
        $postData = array_merge(['token' => $token], $data);

        try {
            /** @var Response $response */
            $response = Http::asJson()
                ->timeout($this->timeout)
                ->post($this->api_url, $postData);

            if (!$response->successful()) {
                return [
                    'error_code' => 3,
                    'error_desc' => "HTTP error code: " . $response->status(),
                    'data' => null
                ];
            }

            $result = $response->json();

            // Auto-retry once if token is expired
            if (!$retried && $this->isTokenExpiredResponse($result)) {
                $newToken = $this->refreshToken();
                if ($newToken) {
                    return $this->postDataWithRetry($data, true);
                }
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('NeofeederService: Exception pada getData', [
                'message' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'error_code' => 2,
                'error_desc' => 'HTTP request error: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Get program studi data.
     * Automatically retries once if the token is expired/invalid.
     *
     * @param string|null $nama_prodi
     * @return array|null
     */
    public function getProdi($nama_prodi = null)
    {
        $token = $this->getToken();

        if (!$token) {
            return null;
        }

        $filter = "";

        if ($nama_prodi) {
            $parts = explode(' ', $nama_prodi, 2);

            if (count($parts) === 2) {
                $jenjang = $parts[0];
                $prodi   = $parts[1];
                $filter = "nama_program_studi='$prodi' AND nama_jenjang_pendidikan='$jenjang'";
            }
        }

        $data = [
            "act" => "GetProdi",
            "filter" => $filter,
            "order" => "",
            "limit" => 0,
            "offset" => 0
        ];

        return $this->getProdiWithRetry($data, $nama_prodi);
    }

    /**
     * POST GetProdi with automatic token refresh on expiry.
     */
    private function getProdiWithRetry(array $data, ?string $nama_prodi, bool $retried = false): ?array
    {
        $token = $this->getToken();
        $postData = array_merge(['token' => $token], $data);

        try {
            /** @var Response $response */
            $response = Http::asJson()
                ->timeout($this->timeout)
                ->post($this->api_url, $postData);

            if (!$response->successful()) {
                Log::error('NeofeederService: Gagal get prodi', [
                    'status' => $response->status()
                ]);
                return null;
            }

            $responseBody = $response->json();

            // Auto-retry once if token is expired
            if (!$retried && $this->isTokenExpiredResponse($responseBody)) {
                $newToken = $this->refreshToken();
                if ($newToken) {
                    return $this->getProdiWithRetry($data, $nama_prodi, true);
                }
            }

            if ($nama_prodi) {
                return $responseBody['data'][0] ?? null;
            }

            return $responseBody['data'] ?? [];
        } catch (\Exception $e) {
            Log::error('NeofeederService: Exception pada getProdi', [
                'message' => $e->getMessage(),
                'nama_prodi' => $nama_prodi
            ]);
            return null;
        }
    }

    /**
     * CRUD operations.
     * Automatically retries once if the token is expired/invalid.
     *
     * @param array $data
     * @return array
     */
    public function crud(array $data)
    {
        $token = $this->getToken();

        if (!$token) {
            return [
                'error_code' => 1,
                'error_desc' => 'Gagal mendapatkan token dari Neofeeder.',
                'data' => null
            ];
        }

        return $this->crudWithRetry($data);
    }

    /**
     * POST CRUD with automatic token refresh on expiry.
     */
    private function crudWithRetry(array $data, bool $retried = false): array
    {
        $token = $this->getToken();
        $postData = array_merge(['token' => $token], $data);

        try {
            /** @var Response $response */
            $response = Http::asJson()
                ->timeout($this->timeout)
                ->retry($this->retryTimes, $this->retrySleep)
                ->post($this->api_url, $postData);

            if (!$response->successful()) {
                return [
                    'error_code' => 3,
                    'error_desc' => "HTTP error code: " . $response->status(),
                    'data' => null
                ];
            }

            $responseBody = $response->json();

            // Auto-retry once if token is expired
            if (!$retried && $this->isTokenExpiredResponse($responseBody)) {
                $newToken = $this->refreshToken();
                if ($newToken) {
                    return $this->crudWithRetry($data, true);
                }
            }

            if (isset($responseBody['error_code']) && $responseBody['error_code'] !== 0) {
                return [
                    'error_code' => 4,
                    'error_desc' => $responseBody['error_desc'] ?? 'Terjadi kesalahan dari API.',
                    'data' => null
                ];
            }

            return [
                'error_code' => 0,
                'error_desc' => '',
                'jumlah' => count($responseBody['data'] ?? []),
                'data' => $responseBody['data'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('NeofeederService: Exception pada crud', [
                'message' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'error_code' => 2,
                'error_desc' => 'HTTP request error: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
}
