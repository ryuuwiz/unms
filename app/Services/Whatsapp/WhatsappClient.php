<?php

namespace App\Services\Whatsapp;

use App\Enums\Sysblas\SysblasProvider;
use App\Models\Sysblas;
use App\Services\Whatsapp\Contracts\WhatsappGatewayDriverInterface;
use App\Services\Whatsapp\Drivers\WablasDriver;
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
        public int $delaySeconds = 3,
        public int $jitterSeconds = 2,
        public bool $simulateTyping = true
    ) {
        $this->host = rtrim($host ?? (string) config('services.wablas.host', 'https://waha.gobilling.id'), '/');
        $this->number = $number ?? (string) config('services.wablas.number', '');
        $this->username = $username ?? '';
        $this->password = $password ?? '';
        $this->apiKey = $apiKey ?: null;
        $this->sessionName = $sessionName ?: 'default';

        if ($provider === SysblasProvider::Wablas->value || $provider === SysblasProvider::Gowa->value) {
            $this->driver = new WablasDriver(
                host: $this->host,
                token: $this->apiKey,
                secret: $this->password,
                number: $this->number
            );
        } else {
            $this->driver = new WahaDriver(
                host: $this->host,
                number: $this->number,
                username: $this->username,
                password: $this->password,
                apiKey: $this->apiKey,
                sessionName: $this->sessionName,
                delaySeconds: $this->delaySeconds,
                jitterSeconds: $this->jitterSeconds,
                simulateTyping: $this->simulateTyping
            );
        }
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
                delaySeconds: $target->delay_detik ?? 3,
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

    public function sendMessage(string $phone, string $message): array
    {
        return $this->driver->sendMessage($phone, $message);
    }

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

    public function pingConnection(): array
    {
        return $this->driver->pingConnection();
    }

    public function getDeviceInfo(): array
    {
        return $this->driver->getDeviceInfo();
    }

    public function getQrCode(): array
    {
        return $this->driver->getQrCode();
    }

    public function startSession(): array
    {
        return $this->driver->startSession();
    }

    public function stopSession(): array
    {
        return $this->driver->stopSession();
    }

    public function restartSession(): array
    {
        return $this->driver->restartSession();
    }

    public function logoutSession(): array
    {
        return $this->driver->logoutSession();
    }

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
