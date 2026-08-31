<?php

namespace App\Services\Whatsapp\Drivers;

use App\Services\Whatsapp\Contracts\WhatsappGatewayDriverInterface;
use App\Services\Whatsapp\WhatsappClient;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WablasDriver implements WhatsappGatewayDriverInterface
{
    protected string $host;

    protected string $token;

    protected string $secret;

    protected string $number;

    public function __construct(
        ?string $host = null,
        ?string $token = null,
        ?string $secret = null,
        ?string $number = null
    ) {
        $this->host = rtrim($host ?? (string) config('services.wablas.host', 'https://tegal.wablas.com'), '/');
        $this->token = $token ?? (string) config('services.wablas.token', '');
        $this->secret = $secret ?? (string) config('services.wablas.secret', '');
        $this->number = $number ?? (string) config('services.wablas.number', '');
    }

    public function sendMessage(string $phone, string $message): array
    {
        $normalizedPhone = WhatsappClient::normalizePhoneNumber($phone);
        if (! $normalizedPhone) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => "Nomor telepon '{$phone}' tidak valid.",
                'data' => [],
            ];
        }

        $url = "{$this->host}/api/v2/send-message";

        try {
            $authorization = $this->secret ? "{$this->token}.{$this->secret}" : $this->token;
            $response = Http::withHeaders([
                'Authorization' => $authorization,
                'Accept' => 'application/json',
            ])->timeout(15)->post($url, [
                'data' => [
                    [
                        'phone' => $normalizedPhone,
                        'message' => $message,
                    ],
                ],
            ]);

            $json = $response->json() ?? [];
            $isSuccess = $response->successful() && (($json['status'] ?? false) === true);

            return [
                'success' => $isSuccess,
                'status' => $isSuccess ? 'success' : 'failed',
                'message' => $json['message'] ?? ($isSuccess ? 'Pesan berhasil dikirim' : "HTTP {$response->status()}"),
                'data' => $json['data'] ?? $json,
            ];
        } catch (Exception $e) {
            Log::error('Wablas API Send Message Exception: '.$e->getMessage(), ['phone' => $normalizedPhone]);

            return [
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    public function pingConnection(): array
    {
        return $this->getDeviceInfo();
    }

    public function getDeviceInfo(): array
    {
        $url = "{$this->host}/api/device/info";

        try {
            $authorization = $this->secret ? "{$this->token}.{$this->secret}" : $this->token;
            $response = Http::withHeaders([
                'Authorization' => $authorization,
                'Accept' => 'application/json',
            ])->timeout(15)->get($url);

            $json = $response->json() ?? [];

            if ($response->successful() && (($json['status'] ?? false) === true)) {
                $data = $json['data'] ?? [];
                $connected = ($data['status'] ?? '') === 'connected';

                return [
                    'connected' => $connected,
                    'phone' => $data['phone'] ?? $this->number,
                    'quota' => (string) ($data['quota'] ?? '-'),
                    'expired_at' => $data['active_period'] ?? null,
                    'message' => $json['message'] ?? ($connected ? 'Device terhubung' : 'Device tidak terhubung'),
                    'raw' => $data,
                ];
            }

            return [
                'connected' => false,
                'phone' => $this->number,
                'quota' => '-',
                'expired_at' => null,
                'message' => $json['message'] ?? "Gagal mengambil status device (HTTP {$response->status()})",
                'raw' => $json,
            ];
        } catch (Exception $e) {
            return [
                'connected' => false,
                'phone' => $this->number,
                'quota' => '-',
                'expired_at' => null,
                'message' => 'Koneksi ke gateway gagal: '.$e->getMessage(),
                'raw' => [],
            ];
        }
    }

    public function getQrCode(): array
    {
        return [
            'success' => false,
            'status' => 'NOT_SUPPORTED',
            'qr' => null,
            'message' => 'Scan QR Code in-app hanya didukung untuk gateway WAHA.',
        ];
    }

    public function startSession(): array
    {
        return ['success' => true, 'message' => 'Not required for WABLAS', 'data' => []];
    }

    public function stopSession(): array
    {
        return ['success' => true, 'message' => 'Not required for WABLAS', 'data' => []];
    }

    public function restartSession(): array
    {
        return ['success' => true, 'message' => 'Not required for WABLAS', 'data' => []];
    }

    public function logoutSession(): array
    {
        return ['success' => true, 'message' => 'Not required for WABLAS', 'data' => []];
    }

    public function checkNumberStatus(string $phone): array
    {
        return [
            'success' => true,
            'exists' => true,
            'phone' => $phone,
            'message' => 'Pengecekan nomor instan hanya didukung untuk gateway WAHA.',
        ];
    }

    public function listSessions(): array
    {
        return [];
    }

    public function startTyping(string $phone): bool
    {
        return true;
    }

    public function stopTyping(string $phone): bool
    {
        return true;
    }

    public function sendSeen(string $phone): bool
    {
        return true;
    }
}
