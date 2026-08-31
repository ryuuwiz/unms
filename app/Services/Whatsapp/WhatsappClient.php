<?php

namespace App\Services\Whatsapp;

use App\Models\Sysblas;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappClient
{
    protected string $host;

    protected string $number;

    protected string $username;

    protected string $password;

    protected string $sessionName;

    public function __construct(
        ?string $host = null,
        ?string $number = null,
        ?string $username = null,
        ?string $password = null
    ) {
        $this->host = rtrim($host ?? (string) config('services.wablas.host', 'https://waha.gobilling.id'), '/');
        $this->number = $number ?? (string) config('services.wablas.number', '');
        $this->username = $username ?? '';
        $this->password = $password ?? '';
        $this->sessionName = 'default';
    }

    public static function forSysblas(?Sysblas $sysblas = null): self
    {
        $target = $sysblas ?? Sysblas::getDefault();

        if ($target) {
            return $target->makeClient();
        }

        return new self;
    }

    public function pingConnection(): array
    {
        return $this->getDeviceInfo();
    }

    public static function normalizePhoneNumber(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if (empty($cleaned)) {
            return null;
        }

        if (str_starts_with($cleaned, '08')) {
            $cleaned = '62'.substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '8')) {
            $cleaned = '62'.$cleaned;
        }

        if (strlen($cleaned) < 10 || strlen($cleaned) > 16 || ! str_starts_with($cleaned, '628')) {
            return null;
        }

        return $cleaned;
    }

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

        $url = "{$this->host}/api/sendText";

        try {
            $payload = [
                'chatId' => "{$normalizedPhone}@c.us",
                'text' => $message,
                'session' => $this->sessionName,
            ];

            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->timeout(15)->post($url, $payload);

            $json = $response->json() ?? [];
            $isSuccess = $response->successful();

            return [
                'success' => $isSuccess,
                'status' => $isSuccess ? 'success' : 'failed',
                'message' => $isSuccess ? 'Pesan berhasil dikirim via WAHA' : "HTTP {$response->status()}",
                'data' => $json,
            ];
        } catch (Exception $e) {
            Log::error('WAHA API Send Message Exception: '.$e->getMessage(), [
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

    public function sendBatchMessages(array $messages): array
    {
        // WAHA doesn't have a native batch API in the free tier typically,
        // so we loop through and send individually, or use a bulk endpoint if available.
        // We'll simulate batch by sending individually but quickly.

        $results = [];
        $hasSuccess = false;

        foreach ($messages as $item) {
            if (! empty($item['phone']) && ! empty($item['message'])) {
                $res = $this->sendMessage($item['phone'], $item['message']);
                $results[] = $res;
                if ($res['success']) {
                    $hasSuccess = true;
                }
            }
        }

        if (empty($results)) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => 'Tidak ada pesan dengan nomor tujuan valid untuk dikirim.',
                'data' => [],
            ];
        }

        return [
            'success' => $hasSuccess,
            'status' => $hasSuccess ? 'success' : 'failed',
            'message' => $hasSuccess ? 'Pesan batch diproses' : 'Semua pesan batch gagal',
            'data' => $results,
        ];
    }

    public function getDeviceInfo(): array
    {
        $url = rtrim($this->host, '/').'/api/sessions';

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Accept' => 'application/json',
                ])->timeout(15)->get($url);

            $json = $response->json() ?? [];

            if ($response->successful() && is_array($json)) {
                $connected = false;
                $sessionInfo = [];

                foreach ($json as $session) {
                    if (($session['name'] ?? '') === $this->sessionName) {
                        $sessionInfo = $session;
                        $connected = ($session['status'] ?? '') === 'WORKING';
                        break;
                    }
                }

                return [
                    'connected' => $connected,
                    'phone' => $this->number,
                    'quota' => 'Unlimited',
                    'expired_at' => '-',
                    'message' => $connected ? 'Device terhubung' : 'Device tidak terhubung',
                    'raw' => $json,
                ];
            }

            return [
                'connected' => false,
                'phone' => $this->number,
                'quota' => '-',
                'expired_at' => null,
                'message' => "Gagal mengambil status device (HTTP {$response->status()})",
                'raw' => $json,
            ];
        } catch (Exception $e) {
            return [
                'connected' => false,
                'phone' => $this->number,
                'quota' => '-',
                'expired_at' => null,
                'message' => 'Koneksi ke host WAHA gagal: '.$e->getMessage(),
                'raw' => [],
            ];
        }
    }
}
