<?php

namespace App\Services\Wablas;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WablasClient
{
    protected string $host;

    protected string $number;

    protected string $token;

    protected string $secret;

    public function __construct(
        ?string $host = null,
        ?string $number = null,
        ?string $token = null,
        ?string $secret = null
    ) {
        $this->host = rtrim($host ?? (string) config('services.wablas.host', 'https://tegal.wablas.com'), '/');
        $this->number = $number ?? (string) config('services.wablas.number', '');
        $this->token = $token ?? (string) config('services.wablas.token', '');
        $this->secret = $secret ?? (string) config('services.wablas.secret', '');
    }

    /**
     * Ambil header Authorization untuk WABLAS.
     * Format: {token}.{secret} atau {token}
     */
    public function getAuthorizationHeader(): string
    {
        $token = trim($this->token);
        $secret = trim($this->secret);

        if (! empty($token) && ! empty($secret)) {
            return "{$token}.{$secret}";
        }

        return $token;
    }

    /**
     * Normalisasi nomor HP format Indonesia ke format standar internasional 628xxx.
     */
    public static function normalizePhoneNumber(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Hapus karakter non-digit
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if (empty($cleaned)) {
            return null;
        }

        // Ganti 08xxx menjadi 628xxx
        if (str_starts_with($cleaned, '08')) {
            $cleaned = '62'.substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '8')) {
            $cleaned = '62'.$cleaned;
        }

        // Validasi panjang minimum nomor telepon Indonesia (628 + min 7 digit = min 10 digit)
        if (strlen($cleaned) < 10 || strlen($cleaned) > 16 || ! str_starts_with($cleaned, '628')) {
            return null;
        }

        return $cleaned;
    }

    /**
     * Kirim pesan WhatsApp tunggal via WABLAS API.
     *
     * @return array{success: bool, status: string, message: string, data: array<string, mixed>}
     */
    public function sendMessage(string $phone, string $message): array
    {
        $normalizedPhone = static::normalizePhoneNumber($phone);

        if (! $normalizedPhone) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => "Nomor telepon '{$phone}' tidak valid untuk format WhatsApp Indonesia.",
                'data' => [],
            ];
        }

        if (empty($this->token)) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => 'WABLAS Token belum dikonfigurasi di environment (.env).',
                'data' => [],
            ];
        }

        $url = "{$this->host}/api/v2/send-message";
        $authHeader = $this->getAuthorizationHeader();

        try {
            /** @var Response $response */
            $response = Http::withHeaders([
                'Authorization' => $authHeader,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(15)->post($url, [
                'data' => [
                    [
                        'phone' => $normalizedPhone,
                        'message' => $message,
                    ],
                ],
            ]);

            $json = $response->json() ?? [];
            $isSuccess = $response->successful() && (($json['status'] ?? '') === true || ($json['status'] ?? '') === 'success' || isset($json['data']));

            return [
                'success' => $isSuccess,
                'status' => $isSuccess ? 'success' : 'failed',
                'message' => $json['message'] ?? ($isSuccess ? 'Pesan berhasil dikirim ke antrean WABLAS' : "HTTP {$response->status()}"),
                'data' => $json,
            ];
        } catch (Exception $e) {
            Log::error('WABLAS API Send Message Exception: '.$e->getMessage(), [
                'phone' => $normalizedPhone,
                'host' => $this->host,
            ]);

            return [
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Kirim pesan batch massal via WABLAS API v2.
     *
     * @param  array<int, array{phone: string, message: string}>  $messages
     * @return array{success: bool, status: string, message: string, data: array<string, mixed>}
     */
    public function sendBatchMessages(array $messages): array
    {
        $validData = [];

        foreach ($messages as $item) {
            $phone = static::normalizePhoneNumber($item['phone'] ?? '');
            if ($phone && ! empty($item['message'])) {
                $validData[] = [
                    'phone' => $phone,
                    'message' => $item['message'],
                ];
            }
        }

        if (empty($validData)) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => 'Tidak ada pesan dengan nomor tujuan valid untuk dikirim.',
                'data' => [],
            ];
        }

        $url = "{$this->host}/api/v2/send-message";
        $authHeader = $this->getAuthorizationHeader();

        try {
            /** @var Response $response */
            $response = Http::withHeaders([
                'Authorization' => $authHeader,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($url, [
                'data' => $validData,
            ]);

            $json = $response->json() ?? [];
            $isSuccess = $response->successful();

            return [
                'success' => $isSuccess,
                'status' => $isSuccess ? 'success' : 'failed',
                'message' => $json['message'] ?? ($isSuccess ? 'Pesan batch berhasil dikirim ke antrean WABLAS' : "HTTP {$response->status()}"),
                'data' => $json,
            ];
        } catch (Exception $e) {
            Log::error('WABLAS API Send Batch Exception: '.$e->getMessage());

            return [
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Ambil informasi status dan koneksi device WABLAS.
     *
     * @return array{connected: bool, phone: string, quota: mixed, expired_at: string|null, message: string, raw: array<string, mixed>}
     */
    public function getDeviceInfo(): array
    {
        if (empty($this->token)) {
            return [
                'connected' => false,
                'phone' => $this->number,
                'quota' => 0,
                'expired_at' => null,
                'message' => 'Token WABLAS belum disetel di file .env.',
                'raw' => [],
            ];
        }

        $url = "{$this->host}/api/device/info";
        $authHeader = $this->getAuthorizationHeader();

        try {
            /** @var Response $response */
            $response = Http::withHeaders([
                'Authorization' => $authHeader,
                'Accept' => 'application/json',
            ])->timeout(10)->get($url);

            $json = $response->json() ?? [];

            if ($response->successful() && isset($json['data'])) {
                $data = $json['data'];
                $statusDevice = strtolower((string) ($data['status'] ?? ''));
                $isConnected = in_array($statusDevice, ['connected', 'active', 'online', '1', 'true'], true);

                return [
                    'connected' => $isConnected,
                    'phone' => (string) ($data['phone'] ?? $this->number),
                    'quota' => $data['quota'] ?? $data['messages_quota'] ?? '-',
                    'expired_at' => (string) ($data['expired_at'] ?? $data['active_period'] ?? '-'),
                    'message' => (string) ($json['message'] ?? ($isConnected ? 'Device terhubung' : 'Device tidak terhubung / Scan QR diperlukan')),
                    'raw' => $json,
                ];
            }

            return [
                'connected' => false,
                'phone' => $this->number,
                'quota' => '-',
                'expired_at' => null,
                'message' => (string) ($json['message'] ?? "Gagal mengambil status device (HTTP {$response->status()})"),
                'raw' => $json,
            ];
        } catch (Exception $e) {
            return [
                'connected' => false,
                'phone' => $this->number,
                'quota' => '-',
                'expired_at' => null,
                'message' => 'Koneksi ke host WABLAS gagal: '.$e->getMessage(),
                'raw' => [],
            ];
        }
    }
}
