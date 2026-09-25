<?php

namespace App\Services\Whatsapp\Drivers;

use App\Services\Whatsapp\Contracts\WhatsappGatewayDriverInterface;
use App\Services\Whatsapp\WhatsappClient;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Driver untuk GOWA (WhatsApp API MultiDevice, self-hosted, https://github.com/aldinokemal/go-whatsapp-web-multidevice).
 *
 * Autentikasi: HTTP Basic Auth (username/password) + header X-Device-Id untuk memilih device
 * pada server GOWA yang di-share antar koneksi (lihat docs/gowa/openapi.yaml).
 *
 * Pairing/QR device dilakukan lewat dashboard GOWA (gowa-ui) itu sendiri, bukan dari aplikasi
 * ini — sehingga method siklus-hidup session (getQrCode/startSession/stopSession/restartSession/
 * logoutSession) di-stub dan tidak disurface ke UI.
 */
class GowaDriver implements WhatsappGatewayDriverInterface
{
    protected string $host;

    protected string $username;

    protected string $password;

    protected string $deviceId;

    protected string $number;

    public function __construct(
        ?string $host = null,
        ?string $username = null,
        ?string $password = null,
        ?string $deviceId = 'default',
        ?string $number = null
    ) {
        $this->host = rtrim($host ?? (string) config('services.gowa.host', 'http://localhost:3000'), '/');
        $this->username = $username ?? (string) config('services.gowa.username', '');
        $this->password = $password ?? (string) config('services.gowa.password', '');
        $this->deviceId = $deviceId ?: 'default';
        $this->number = $number ?? (string) config('services.gowa.number', '');
    }

    /**
     * Dapatkan HTTP client yang sudah terautentikasi (Basic Auth + X-Device-Id).
     */
    protected function http(): PendingRequest
    {
        $client = Http::timeout(15)->withHeaders([
            'Accept' => 'application/json',
            'X-Device-Id' => $this->deviceId,
        ]);

        if ($this->username !== '' || $this->password !== '') {
            $client = $client->withBasicAuth($this->username, $this->password);
        }

        return $client;
    }

    /**
     * Daftarkan URL webhook aplikasi ini ke device GOWA (PATCH /devices/{device_id}/webhook), agar
     * event pesan masuk/status pengiriman diteruskan ke endpoint webhook terpadu aplikasi. Dipanggil
     * best-effort saat admin menyimpan koneksi provider Gowa — kegagalan tidak boleh membatalkan
     * penyimpanan koneksi.
     *
     * @return array{success: bool, message: string}
     */
    public function registerWebhook(string $webhookUrl, ?string $webhookSecret = null): array
    {
        $url = "{$this->host}/devices/{$this->deviceId}/webhook";

        try {
            $response = $this->http()->patch($url, array_filter([
                'webhook_url' => $webhookUrl,
                'webhook_secret' => $webhookSecret,
            ], fn ($value) => $value !== null && $value !== ''));

            $json = $response->json() ?? [];

            return [
                'success' => $response->successful(),
                'message' => $json['message'] ?? ($response->successful() ? 'Webhook berhasil didaftarkan ke GOWA.' : "Gagal mendaftarkan webhook (HTTP {$response->status()})"),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Gagal menghubungi server GOWA untuk registrasi webhook: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Normalisasi nomor telepon ke format JID WhatsApp yang diharapkan GOWA (628xxx@s.whatsapp.net).
     */
    protected function toJid(string $phone): ?string
    {
        $normalized = WhatsappClient::normalizePhoneNumber($phone);
        if (! $normalized) {
            return null;
        }

        return str_contains($phone, '@') ? $phone : "{$normalized}@s.whatsapp.net";
    }

    public function sendMessage(string $phone, string $message): array
    {
        $jid = $this->toJid($phone);
        if (! $jid) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => "Nomor telepon '{$phone}' tidak valid.",
                'data' => [],
            ];
        }

        $url = "{$this->host}/send/message";

        try {
            $response = $this->http()->post($url, [
                'phone' => $jid,
                'message' => $message,
            ]);

            $json = $response->json() ?? [];
            $isSuccess = $response->successful() && strtoupper((string) ($json['code'] ?? '')) === 'SUCCESS';

            return [
                'success' => $isSuccess,
                'status' => $isSuccess ? 'success' : WhatsappClient::statusGagalHttp($response->status()),
                'message' => $json['message'] ?? ($isSuccess ? 'Pesan berhasil dikirim via GOWA' : "HTTP {$response->status()}"),
                'data' => $json['results'] ?? $json,
            ];
        } catch (Exception $e) {
            Log::error('GOWA API Send Message Exception: '.$e->getMessage(), [
                'phone' => $jid,
                'host' => $this->host,
                'device_id' => $this->deviceId,
            ]);

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
        $url = "{$this->host}/devices/{$this->deviceId}";

        try {
            $response = $this->http()->get($url);
            $json = $response->json() ?? [];

            if ($response->successful()) {
                $results = (array) ($json['results'] ?? []);
                $state = (string) ($results['state'] ?? 'disconnected');
                $connected = in_array($state, ['connected', 'logged_in'], true);

                return [
                    'connected' => $connected,
                    'phone' => $results['phone_number'] ?? $this->number,
                    'quota' => 'Unlimited',
                    'expired_at' => '-',
                    'message' => $json['message'] ?? ($connected ? "Device '{$this->deviceId}' terhubung" : "Device '{$this->deviceId}' tidak terhubung ({$state})"),
                    'session_status' => $connected ? 'WORKING' : strtoupper($state),
                    'raw' => $results,
                ];
            }

            return [
                'connected' => false,
                'phone' => $this->number,
                'quota' => '-',
                'expired_at' => null,
                'message' => $json['message'] ?? "Gagal mengambil status device (HTTP {$response->status()})",
                'session_status' => 'ERROR',
                'raw' => $json,
            ];
        } catch (Exception $e) {
            return [
                'connected' => false,
                'phone' => $this->number,
                'quota' => '-',
                'expired_at' => null,
                'message' => 'Koneksi ke host GOWA gagal: '.$e->getMessage(),
                'session_status' => 'UNREACHABLE',
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
            'message' => 'Pairing device GOWA dilakukan langsung lewat dashboard GOWA (gowa-ui), bukan dari aplikasi ini.',
        ];
    }

    public function startSession(): array
    {
        return ['success' => true, 'message' => 'Siklus hidup session dikelola lewat dashboard GOWA, tidak diperlukan dari aplikasi ini.', 'data' => []];
    }

    public function stopSession(): array
    {
        return ['success' => true, 'message' => 'Siklus hidup session dikelola lewat dashboard GOWA, tidak diperlukan dari aplikasi ini.', 'data' => []];
    }

    public function restartSession(): array
    {
        return ['success' => true, 'message' => 'Siklus hidup session dikelola lewat dashboard GOWA, tidak diperlukan dari aplikasi ini.', 'data' => []];
    }

    public function logoutSession(): array
    {
        return ['success' => true, 'message' => 'Siklus hidup session dikelola lewat dashboard GOWA, tidak diperlukan dari aplikasi ini.', 'data' => []];
    }

    public function checkNumberStatus(string $phone): array
    {
        $normalizedPhone = WhatsappClient::normalizePhoneNumber($phone);
        if (! $normalizedPhone) {
            return [
                'success' => false,
                'exists' => false,
                'phone' => $phone,
                'message' => 'Format nomor telepon tidak valid.',
            ];
        }

        $url = "{$this->host}/user/check";

        try {
            $response = $this->http()->get($url, ['phone' => $normalizedPhone]);
            $json = $response->json() ?? [];
            $exists = (bool) ($json['results']['is_on_whatsapp'] ?? false);

            return [
                'success' => $response->successful(),
                'exists' => $exists,
                'phone' => $normalizedPhone,
                'message' => $exists ? 'Nomor terdaftar di WhatsApp.' : 'Nomor tidak terdaftar di WhatsApp.',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'exists' => false,
                'phone' => $normalizedPhone,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function listSessions(): array
    {
        $url = "{$this->host}/devices";

        try {
            $response = $this->http()->get($url);
            $json = $response->json() ?? [];

            if ($response->successful() && is_array($json['results'] ?? null)) {
                $devices = [];
                foreach ($json['results'] as $device) {
                    $state = (string) ($device['state'] ?? 'disconnected');

                    $devices[] = [
                        'name' => $device['id'] ?? '',
                        'status' => $state,
                        'phone' => $device['phone_number'] ?? null,
                        'pushName' => $device['display_name'] ?? null,
                        'connected' => in_array($state, ['connected', 'logged_in'], true),
                        'raw' => $device,
                    ];
                }

                return $devices;
            }

            return [];
        } catch (Exception $e) {
            Log::warning('Gagal mengambil daftar device dari GOWA: '.$e->getMessage());

            return [];
        }
    }

    public function startTyping(string $phone): bool
    {
        return $this->sendChatPresence($phone, 'start');
    }

    public function stopTyping(string $phone): bool
    {
        return $this->sendChatPresence($phone, 'stop');
    }

    protected function sendChatPresence(string $phone, string $action): bool
    {
        $jid = $this->toJid($phone);
        if (! $jid) {
            return false;
        }

        try {
            $response = $this->http()->post("{$this->host}/send/chat-presence", [
                'phone' => $jid,
                'action' => $action,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::debug("GOWA {$action}Typing failed: ".$e->getMessage());

            return false;
        }
    }

    public function sendSeen(string $phone): bool
    {
        // GOWA menandai pesan terbaca per message_id (POST /message/{id}/read), bukan per-chat
        // seperti signature interface ini. Tidak ada cara aman menebak message_id terakhir tanpa
        // riwayat chat, jadi ini best-effort no-op sampai interface diperluas untuk GOWA.
        return true;
    }
}
