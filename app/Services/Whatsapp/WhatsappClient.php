<?php

namespace App\Services\Whatsapp;

use App\Enums\Sysblas\SysblasProvider;
use App\Models\Sysblas;
use App\Services\Whatsapp\Contracts\WhatsappGatewayDriverInterface;
use App\Services\Whatsapp\Drivers\GowaDriver;
use App\Services\Whatsapp\Drivers\WahaDriver;

class WhatsappClient
{
    protected WhatsappGatewayDriverInterface $driver;

    protected string $host;

    protected string $number;

    protected string $username;

    protected string $password;

    protected ?string $apiKey;

    protected string $sessionName;

    public function __construct(
        ?string $host = null,
        ?string $number = null,
        ?string $username = null,
        ?string $password = null,
        ?string $apiKey = null,
        ?string $sessionName = 'default',
        ?string $provider = 'waha',
        public int $delaySeconds = 300,
        public int $jitterSeconds = 2,
        public bool $simulateTyping = true
    ) {
        $providerValue = $provider ?: SysblasProvider::Waha->value;
        $defaultHost = $providerValue === SysblasProvider::Gowa->value
            ? (string) config('services.gowa.host', 'http://localhost:3000')
            : (string) config('services.waha.host', 'https://waha.gobilling.id');
        $defaultNumber = $providerValue === SysblasProvider::Gowa->value
            ? (string) config('services.gowa.number', '')
            : (string) config('services.waha.number', '');

        $this->host = rtrim($host ?? $defaultHost, '/');
        $this->number = $number ?? $defaultNumber;
        $this->username = $username ?? '';
        $this->password = $password ?? '';
        $this->apiKey = $apiKey ?: null;
        $this->sessionName = $sessionName ?: 'default';

        $this->driver = match ($providerValue) {
            SysblasProvider::Gowa->value => new GowaDriver(
                host: $this->host,
                username: $this->username,
                password: $this->password,
                deviceId: $this->sessionName,
                number: $this->number
            ),
            default => new WahaDriver(
                host: $this->host,
                number: $this->number,
                username: $this->username,
                password: $this->password,
                apiKey: $this->apiKey,
                sessionName: $this->sessionName,
                delaySeconds: $this->delaySeconds,
                jitterSeconds: $this->jitterSeconds,
                simulateTyping: $this->simulateTyping
            ),
        };
    }

    public static function forSysblas(?Sysblas $sysblas = null): self
    {
        $target = $sysblas ?? Sysblas::getDefault();

        if ($target) {
            return new self(
                host: $target->url_api ?: 'https://waha.gobilling.id',
                number: $target->nomor ?: '',
                username: $target->username ?: '',
                password: $target->password ?: '',
                apiKey: $target->api_token ?: null,
                sessionName: $target->session_name ?: 'default',
                provider: $target->provider->value,
                delaySeconds: $target->delay_detik ?? 300,
                jitterSeconds: $target->jitter_detik ?? 2,
                simulateTyping: $target->is_typing_simulation ?? true
            );
        }

        return new self;
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

    /**
     * Klasifikasi respons HTTP gagal dari gateway: `rate_limited` (429) dan `error` (5xx) dicoba
     * ulang oleh KirimWaBlastJob; `unauthorized` (401/403) berarti gateway salah konfigurasi;
     * sisanya `failed` (permanen, mis. nomor ditolak).
     */
    public static function statusGagalHttp(int $kodeHttp): string
    {
        return match (true) {
            $kodeHttp === 429 => 'rate_limited',
            in_array($kodeHttp, [401, 403], true) => 'unauthorized',
            $kodeHttp >= 500 => 'error',
            default => 'failed',
        };
    }

    /**
     * @return array{success: bool, status: string, message: string, data: array<string, mixed>}
     */
    public function sendMessage(string $phone, string $message): array
    {
        return $this->driver->sendMessage($phone, $message);
    }

    /**
     * @param  array<int, array{phone?: string, message?: string}>  $messages
     * @return array{success: bool, status: string, message: string, data: array<int, array{success: bool, status: string, message: string, data: array<string, mixed>}>}
     */
    public function sendBatchMessages(array $messages, ?int $delaySeconds = null, ?int $jitterSeconds = null): array
    {
        $results = [];
        $hasSuccess = false;
        $delay = $delaySeconds ?? $this->delaySeconds;
        $jitter = $jitterSeconds ?? $this->jitterSeconds;

        foreach ($messages as $index => $item) {
            if (! empty($item['phone']) && ! empty($item['message'])) {
                if ($index > 0 && $delay > 0) {
                    $sleepTime = $delay + ($jitter > 0 ? rand(0, $jitter) : 0);
                    sleep($sleepTime);
                }

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

    public function startTyping(string $phone): bool
    {
        return $this->driver->startTyping($phone);
    }

    public function stopTyping(string $phone): bool
    {
        return $this->driver->stopTyping($phone);
    }

    public function sendSeen(string $phone): bool
    {
        return $this->driver->sendSeen($phone);
    }

    /**
     * @return array{connected: bool, phone: string, quota: string, expired_at: ?string, message: string, session_status: string, raw: array<string, mixed>}
     */
    public function pingConnection(): array
    {
        return $this->driver->pingConnection();
    }

    /**
     * @return array{connected: bool, phone: string, quota: string, expired_at: ?string, message: string, session_status: string, raw: array<string, mixed>}
     */
    public function getDeviceInfo(): array
    {
        return $this->driver->getDeviceInfo();
    }

    /**
     * @return array{success: bool, status: string, qr: ?string, message: string}
     */
    public function getQrCode(): array
    {
        return $this->driver->getQrCode();
    }

    /**
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function startSession(): array
    {
        return $this->driver->startSession();
    }

    /**
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function stopSession(): array
    {
        return $this->driver->stopSession();
    }

    /**
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function restartSession(): array
    {
        return $this->driver->restartSession();
    }

    /**
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function logoutSession(): array
    {
        return $this->driver->logoutSession();
    }

    /**
     * @return array{success: bool, exists: bool, phone: string, message: string}
     */
    public function checkNumberStatus(string $phone): array
    {
        return $this->driver->checkNumberStatus($phone);
    }

    /**
     * Dapatkan daftar seluruh session yang tersedia di gateway.
     *
     * @return array<int, array{name: string, status: string, phone: ?string, pushName: ?string, connected: bool, raw: array<string, mixed>}>
     */
    public function listSessions(): array
    {
        return $this->driver->listSessions();
    }

    public function getDriver(): WhatsappGatewayDriverInterface
    {
        return $this->driver;
    }
}
