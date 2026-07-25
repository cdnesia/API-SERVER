<?php

namespace App\Services;

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

    public function __construct()
    {
        $this->username  = config('feeder.username');
        $this->password  = config('feeder.password');
        $this->api_url   = config('feeder.url');
        $this->timeout   = config('feeder.timeout', 30);
        $this->retryTimes = config('feeder.retry.times', 2);
        $this->retrySleep = config('feeder.retry.sleep', 500);
    }

    /**
     * Get authentication token
     *
     * @return string|null
     */
    private function getToken()
    {
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
                    return $body['data']['token'] ?? null;
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
     * Get data from Neofeeder API
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

            return $response->json();
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
     * Get program studi data
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
     * CRUD operations
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
