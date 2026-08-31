<?php

namespace App\Services\Whatsapp\Drivers;

use App\Services\Whatsapp\Contracts\WhatsappGatewayDriverInterface;
use App\Services\Whatsapp\WhatsappClient;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WahaDriver implements WhatsappGatewayDriverInterface
{
    protected string $host;

    protected string $number;

    protected string $sessionName;

    protected string $username;

    protected string $password;

    protected ?string $apiKey;

    public function __construct(
        ?string $host = null,
        ?string $number = null,
        ?string $username = null,
        ?string $password = null,
        ?string $apiKey = null,
        ?string $sessionName = 'default',
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
    }

    /**
     * Dapatkan HTTP client yang sudah terautentikasi (Basic Auth / API Key).
     */
    protected function http(): PendingRequest
    {
        $client = Http::timeout(15)->withHeaders([
            'Accept' => 'application/json',
        ]);

        if (! empty($this->apiKey)) {
            $client->withHeaders([
                'X-Api-Key' => $this->apiKey,
            ]);
        }

        if (! empty($this->username) || ! empty($this->password)) {
            $client->withBasicAuth($this->username, $this->password);
        }

        return $client;
    }

    /**
     * Kirim pesan teks WhatsApp dengan proteksi rate limit dan anti-ban.
     */
    public function sendMessage(string $phone, string $message): array
    {
        $normalizedPhone = WhatsappClient::normalizePhoneNumber($phone);

        if (! $normalizedPhone) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => "Nomor telepon '{$phone}' tidak valid untuk format WhatsApp Indonesia.",
                'data' => [],
            ];
        }

        // Anti-ban: Simulasi aktivitas mengetik (typing presence) sebelum pengiriman teks
        if ($this->simulateTyping) {
            $this->startTyping($normalizedPhone);
            // Jeda mikro proporsional terhadap panjang teks (500ms - 2000ms) untuk menyerupai manusia
            $typingDurationMs = min(2000, max(500, (int) (strlen($message) * 12)));
            usleep($typingDurationMs * 1000);
            $this->stopTyping($normalizedPhone);
        }

        $url = "{$this->host}/api/sendText";

        try {
            $payload = [
                'chatId' => "{$normalizedPhone}@c.us",
                'text' => $message,
                'session' => $this->sessionName,
            ];

            $response = $this->http()->post($url, $payload);
            $json = $response->json() ?? [];
            $isSuccess = $response->successful();

            if (! $isSuccess && $response->status() === 429) {
                Log::warning("WAHA API Rate Limit Hit (HTTP 429) pada session '{$this->sessionName}'", [
                    'phone' => $normalizedPhone,
                    'host' => $this->host,
                ]);

                return [
                    'success' => false,
                    'status' => 'rate_limited',
                    'message' => 'Laju pengiriman WAHA melebihi batas (HTTP 429 Too Many Requests).',
                    'data' => $json,
                ];
            }

            return [
                'success' => $isSuccess,
                'status' => $isSuccess ? 'success' : 'failed',
                'message' => $isSuccess ? 'Pesan berhasil dikirim via WAHA' : ($json['message'] ?? "HTTP {$response->status()}"),
                'data' => $json,
            ];
        } catch (Exception $e) {
            Log::error('WAHA API Send Message Exception: '.$e->getMessage(), [
                'phone' => $normalizedPhone,
                'host' => $this->host,
                'session' => $this->sessionName,
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
     * Ping koneksi ke session WAHA.
     */
    public function pingConnection(): array
    {
        return $this->getDeviceInfo();
    }

    /**
     * Ambil informasi device dan status session.
     */
    public function getDeviceInfo(): array
    {
        $url = "{$this->host}/api/sessions";

        try {
            $response = $this->http()->get($url);
            $json = $response->json() ?? [];

            if ($response->successful() && is_array($json)) {
                $connected = false;
                $sessionInfo = [];
                $statusText = 'UNKNOWN';

                foreach ($json as $session) {
                    if (strcasecmp((string) ($session['name'] ?? ''), $this->sessionName) === 0) {
                        $sessionInfo = $session;
                        $statusText = $session['status'] ?? 'UNKNOWN';
                        $connected = ($statusText === 'WORKING');
                        break;
                    }
                }

                // Fallback jika nama tidak cocok persis tapi hanya ada 1 session di server WAHA
                if (! $sessionInfo && count($json) === 1) {
                    $sessionInfo = $json[0];
                    $statusText = $sessionInfo['status'] ?? 'UNKNOWN';
                    $connected = ($statusText === 'WORKING');
                }

                $devicePhone = $this->number;
                if (! empty($sessionInfo['me']['id'])) {
                    $extracted = explode('@', (string) $sessionInfo['me']['id'])[0];
                    if ($extracted) {
                        $devicePhone = $extracted;
                    }
                }

                $pushName = $sessionInfo['me']['pushName'] ?? '';
                $nameSuffix = $pushName ? " ({$pushName})" : '';

                $message = match ($statusText) {
                    'WORKING' => "Session WhatsApp '{$this->sessionName}' terhubung & aktif{$nameSuffix}.",
                    'SCAN_QR_CODE' => 'Session menunggu scan QR Code WhatsApp.',
                    'STARTING' => 'Session sedang memulai proses booting.',
                    'FAILED' => 'Session gagal terhubung ke WhatsApp Web.',
                    'STOPPED' => 'Session dihentikan (Stopped).',
                    default => $connected ? "Device terhubung{$nameSuffix}" : "Status session: {$statusText}",
                };

                return [
                    'connected' => $connected,
                    'phone' => $devicePhone,
                    'quota' => 'Unlimited',
                    'expired_at' => '-',
                    'message' => $message,
                    'session_status' => $statusText,
                    'raw' => $sessionInfo ?: $json,
                ];
            }

            return [
                'connected' => false,
                'phone' => $this->number,
                'quota' => '-',
                'expired_at' => null,
                'message' => "Gagal mengambil status device (HTTP {$response->status()})",
                'session_status' => 'ERROR',
                'raw' => is_array($json) ? $json : [],
            ];
        } catch (Exception $e) {
            return [
                'connected' => false,
                'phone' => $this->number,
                'quota' => '-',
                'expired_at' => null,
                'message' => 'Koneksi ke host WAHA gagal: '.$e->getMessage(),
                'session_status' => 'UNREACHABLE',
                'raw' => [],
            ];
        }
    }

    /**
     * Ambil QR Code pairing WhatsApp Web.
     */
    public function getQrCode(): array
    {
        $url = "{$this->host}/api/{$this->sessionName}/auth/qr";

        try {
            $response = $this->http()->withHeaders([
                'Accept' => 'image/png, application/json',
            ])->get($url, ['format' => 'image']);

            if ($response->successful()) {
                $contentType = $response->header('Content-Type') ?? '';

                if (str_contains($contentType, 'image')) {
                    $base64 = base64_encode($response->body());

                    return [
                        'success' => true,
                        'status' => 'SCAN_QR_CODE',
                        'qr' => "data:image/png;base64,{$base64}",
                        'message' => 'QR Code berhasil dimuat. Silakan scan dengan WhatsApp.',
                    ];
                }

                $json = $response->json() ?? [];
                if (! empty($json['data'])) {
                    $raw = $json['data'];
                    $qr = str_starts_with($raw, 'data:') ? $raw : "data:image/png;base64,{$raw}";

                    return [
                        'success' => true,
                        'status' => 'SCAN_QR_CODE',
                        'qr' => $qr,
                        'message' => 'QR Code berhasil dimuat.',
                    ];
                }
            }

            $json = $response->json() ?? [];

            return [
                'success' => false,
                'status' => $json['status'] ?? 'NOT_READY',
                'qr' => null,
                'message' => $json['message'] ?? 'QR Code belum siap atau session sudah terhubung.',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'status' => 'ERROR',
                'qr' => null,
                'message' => 'Gagal mengambil QR Code: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Jalankan session WAHA.
     */
    public function startSession(): array
    {
        $url = "{$this->host}/api/sessions/start";

        try {
            $response = $this->http()->post($url, [
                'name' => $this->sessionName,
            ]);

            return [
                'success' => $response->successful(),
                'message' => $response->successful() ? 'Session berhasil dimulai.' : 'Gagal memulai session.',
                'data' => $response->json() ?? [],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Hentikan session WAHA.
     */
    public function stopSession(): array
    {
        $url = "{$this->host}/api/sessions/stop";

        try {
            $response = $this->http()->post($url, [
                'name' => $this->sessionName,
            ]);

            return [
                'success' => $response->successful(),
                'message' => $response->successful() ? 'Session berhasil dihentikan.' : 'Gagal menghentikan session.',
                'data' => $response->json() ?? [],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Restart session WAHA.
     */
    public function restartSession(): array
    {
        $url = "{$this->host}/api/sessions/{$this->sessionName}/restart";

        try {
            $response = $this->http()->post($url);

            return [
                'success' => $response->successful(),
                'message' => $response->successful() ? 'Session berhasil dimuat ulang (restarted).' : 'Gagal restart session.',
                'data' => $response->json() ?? [],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Logout session WAHA.
     */
    public function logoutSession(): array
    {
        $url = "{$this->host}/api/sessions/logout";

        try {
            $response = $this->http()->post($url, [
                'name' => $this->sessionName,
            ]);

            return [
                'success' => $response->successful(),
                'message' => $response->successful() ? 'Session berhasil logout.' : 'Gagal logout session.',
                'data' => $response->json() ?? [],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Cek apakah nomor terdaftar di WhatsApp.
     */
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

        $url = "{$this->host}/api/contacts/check-exists";

        try {
            $response = $this->http()->get($url, [
                'phone' => $normalizedPhone,
                'session' => $this->sessionName,
            ]);

            $json = $response->json() ?? [];
            $exists = (bool) ($json['numberExists'] ?? false);

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

    /**
     * Ambil seluruh session yang terdaftar di WAHA.
     */
    public function listSessions(): array
    {
        $url = "{$this->host}/api/sessions";

        try {
            $response = $this->http()->get($url, ['all' => 'true']);
            $json = $response->json() ?? [];

            if ($response->successful() && is_array($json)) {
                $sessions = [];
                foreach ($json as $session) {
                    $phone = null;
                    if (! empty($session['me']['id'])) {
                        $phone = explode('@', (string) $session['me']['id'])[0];
                    }
                    $status = $session['status'] ?? 'UNKNOWN';

                    $sessions[] = [
                        'name' => $session['name'] ?? '',
                        'status' => $status,
                        'phone' => $phone,
                        'pushName' => $session['me']['pushName'] ?? null,
                        'connected' => ($status === 'WORKING'),
                        'raw' => $session,
                    ];
                }

                return $sessions;
            }

            return [];
        } catch (Exception $e) {
            Log::warning('Gagal mengambil list sessions dari WAHA: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Kirim status sedang mengetik (typing presence) ke chat.
     */
    public function startTyping(string $phone): bool
    {
        $normalizedPhone = WhatsappClient::normalizePhoneNumber($phone);
        if (! $normalizedPhone) {
            return false;
        }

        try {
            $url = "{$this->host}/api/startTyping";
            $response = $this->http()->post($url, [
                'session' => $this->sessionName,
                'chatId' => "{$normalizedPhone}@c.us",
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::debug('WAHA startTyping failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Hentikan status sedang mengetik di chat.
     */
    public function stopTyping(string $phone): bool
    {
        $normalizedPhone = WhatsappClient::normalizePhoneNumber($phone);
        if (! $normalizedPhone) {
            return false;
        }

        try {
            $url = "{$this->host}/api/stopTyping";
            $response = $this->http()->post($url, [
                'session' => $this->sessionName,
                'chatId' => "{$normalizedPhone}@c.us",
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::debug('WAHA stopTyping failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Tandai pesan telah dibaca (sendSeen).
     */
    public function sendSeen(string $phone): bool
    {
        $normalizedPhone = WhatsappClient::normalizePhoneNumber($phone);
        if (! $normalizedPhone) {
            return false;
        }

        try {
            $url = "{$this->host}/api/sendSeen";
            $response = $this->http()->post($url, [
                'session' => $this->sessionName,
                'chatId' => "{$normalizedPhone}@c.us",
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::debug('WAHA sendSeen failed: '.$e->getMessage());

            return false;
        }
    }
}
