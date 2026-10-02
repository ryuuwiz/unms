<?php

namespace App\Services\Whatsapp;

use App\Enums\StatusWebhookLog;
use App\Enums\Sysblas\SysblasProvider;
use App\Enums\Ticket\StatusTicket;
use App\Enums\Wa\StatusAntrianWa;
use App\Models\AntrianWaBlast;
use App\Models\Pelanggan;
use App\Models\Sysblas;
use App\Models\Ticket;
use App\Models\TicketHistori;
use App\Models\WebhookLog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Sentry\State\Scope;

class WhatsappWebhookService
{
    /**
     * Proses payload webhook WhatsApp secara sinkron: mencatat WebhookLog lalu langsung
     * menanganinya. Dipakai oleh `whatsapp:simulate-webhook` (CLI lokal, bukan HTTP publik).
     * Jalur HTTP publik (`WhatsappWebhookController`) mencatat WebhookLog dan memverifikasi
     * signature GOWA lebih dulu, lalu memproses via `ProcessWhatsappWebhookJob`.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: bool, type: string, message: string}
     */
    public function process(array $payload): array
    {
        return $this->handlePayload($payload, $this->logWebhook($payload));
    }

    /**
     * Tangani payload event GOWA `{event, session, payload}` yang WebhookLog-nya sudah dicatat.
     * Format flat legacy `{phone, message}` / `{phone, status}` tidak lagi diproses (ADR-0067).
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: bool, type: string, message: string}
     */
    public function handlePayload(array $payload, ?WebhookLog $log): array
    {
        try {
            if (! isset($payload['event'], $payload['payload'])) {
                $log?->update(['status_proses' => StatusWebhookLog::Diabaikan]);

                return [
                    'status' => true,
                    'type' => 'unknown',
                    'message' => 'Payload diakui namun tidak memerlukan tindakan.',
                ];
            }

            $result = $this->handleGowaEvent($payload);
            $log?->update(['status_proses' => StatusWebhookLog::Diproses]);

            return $result;
        } catch (\Throwable $e) {
            $log?->update([
                'status_proses' => StatusWebhookLog::Gagal,
                'catatan_error' => $e->getMessage(),
            ]);

            Log::error('WhatsApp Webhook Processing Error: '.$e->getMessage(), ['payload' => $payload]);

            // Capture eksplisit ke Sentry: jalur ini sinkron (dipanggil dari CLI simulate
            // command) tanpa hook failed() milik queue job untuk menangkapnya -- lihat
            // ProcessWhatsappWebhookJob::failed() untuk jalur HTTP webhook produksi asli.
            \Sentry\configureScope(function (Scope $scope) use ($log): void {
                $scope->setContext('whatsapp_webhook', ['webhook_log_id' => $log?->id]);
            });
            \Sentry\captureException($e);

            return [
                'status' => false,
                'type' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cari koneksi Sysblas provider GOWA berdasarkan device_id (session_name) yang dikirim
     * dalam payload webhook (`session` atau `device_id`).
     *
     * @param  array<string, mixed>  $payload
     */
    public function resolveGowaSysblas(array $payload): ?Sysblas
    {
        $deviceId = (string) ($payload['session'] ?? $payload['device_id'] ?? '');
        if ($deviceId === '') {
            return null;
        }

        return Sysblas::query()
            ->where('provider', SysblasProvider::Gowa)
            ->where('session_name', $deviceId)
            ->first();
    }

    /**
     * Verifikasi HMAC-SHA256 signature webhook GOWA memakai `webhook_secret` koneksi
     * (disimpan pada kolom `api_secret`). Koneksi tanpa secret selalu ditolak: tanpa secret,
     * pengirim tidak dapat dibuktikan berasal dari GOWA (ADR-0067).
     *
     * Catatan: nama header signature GOWA belum terdokumentasi di openapi.yaml (hanya field
     * registrasi `webhook_secret` yang tercatat) — nama header di bawah ini perlu dikonfirmasi
     * ulang terhadap instance GOWA yang benar-benar berjalan dan disesuaikan bila berbeda.
     */
    public function verifyGowaSignature(Request $request, Sysblas $sysblas): bool
    {
        $secret = (string) ($sysblas->api_secret ?? '');
        if ($secret === '') {
            return false;
        }

        $signatureHeader = (string) ($request->header('X-Gowa-Signature') ?? $request->header('X-Hub-Signature-256') ?? '');
        $signature = preg_replace('/^sha256=/', '', $signatureHeader) ?? '';

        if ($signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Tangani event terstruktur GOWA (konvensi nama event sama dengan WAHA).
     *
     * @param  array<string, mixed>  $data
     * @return array{status: bool, type: string, message: string}
     */
    protected function handleGowaEvent(array $data): array
    {
        $event = (string) ($data['event'] ?? '');
        $session = (string) ($data['session'] ?? 'default');
        $payload = (array) ($data['payload'] ?? []);

        switch ($event) {
            case 'message.ack':
                $this->handleWahaMessageAck($payload, $session);

                return [
                    'status' => true,
                    'type' => 'message_ack',
                    'message' => 'Status ACK pengiriman pesan WAHA berhasil diperbarui.',
                ];

            case 'session.status':
                $status = (string) ($payload['status'] ?? 'UNKNOWN');
                $this->handleWahaSessionStatus($session, $status);

                return [
                    'status' => true,
                    'type' => 'session_status',
                    'message' => "Status session '{$session}' diperbarui: {$status}.",
                ];

            case 'message':
            case 'message.any':
                // Abaikan pesan yang dikirim oleh bot sendiri
                if (! empty($payload['fromMe'])) {
                    return [
                        'status' => true,
                        'type' => 'outbound_ignored',
                        'message' => 'Pesan keluar (fromMe) diabaikan.',
                    ];
                }

                $from = (string) ($payload['from'] ?? '');
                $rawPhone = explode('@', $from)[0];
                $body = (string) ($payload['body'] ?? '');

                $this->handleIncomingMessage($rawPhone, $body);

                return [
                    'status' => true,
                    'type' => 'incoming_message',
                    'message' => 'Pesan masuk dicatat.',
                ];

            default:
                return [
                    'status' => true,
                    'type' => 'unhandled_event',
                    'message' => "Event WAHA '{$event}' diakui tanpa aksi lanjutan.",
                ];
        }
    }

    /**
     * Update status AntrianWaBlast berdasarkan event WAHA message.ack.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleWahaMessageAck(array $payload, string $session): void
    {
        $to = (string) ($payload['to'] ?? $payload['from'] ?? '');
        $rawPhone = explode('@', $to)[0];
        $phone = WhatsappClient::normalizePhoneNumber($rawPhone);
        $ack = $payload['ack'] ?? null;
        $ackName = strtolower((string) ($payload['ackName'] ?? ''));

        $query = AntrianWaBlast::query()->latest('id');

        if ($phone) {
            $query->where('no_hp_tujuan', $phone);
        }

        $antrian = $query->where('created_at', '>=', Carbon::now()->subDays(3))->first();

        if (! $antrian) {
            return;
        }

        $existingLog = (array) ($antrian->response_log ?? []);
        $existingLog['waha_ack'] = [
            'ack' => $ack,
            'ackName' => $ackName,
            'session' => $session,
            'payload' => $payload,
            'received_at' => Carbon::now()->toIso8601String(),
        ];

        // WAHA ACK values: 1 = SERVER/SENT, 2 = DEVICE/DELIVERED, 3 = READ, 4 = PLAYED, -1/0/FAILED = ERROR
        if ($ack >= 1 || in_array($ackName, ['server', 'device', 'read', 'played', 'delivery'], true)) {
            $antrian->update([
                'status' => StatusAntrianWa::Terkirim,
                'dikirim_pada' => $antrian->dikirim_pada ?? Carbon::now(),
                'response_log' => $existingLog,
                'pesan_error' => null,
            ]);
        } elseif ($ack < 0 || in_array($ackName, ['error', 'failed'], true)) {
            $antrian->update([
                'status' => StatusAntrianWa::Gagal,
                'pesan_error' => "Gagal terkirim via WAHA ACK: {$ackName}",
                'response_log' => $existingLog,
            ]);
        }
    }

    /**
     * Tangani perubahan status session WAHA.
     */
    protected function handleWahaSessionStatus(string $sessionName, string $status): void
    {
        $sysblas = Sysblas::query()
            ->where('session_name', $sessionName)
            ->first();

        if (! $sysblas) {
            return;
        }

        if ($status === 'WORKING') {
            $sysblas->update(['is_aktif' => true]);
        } elseif (in_array($status, ['STOPPED', 'FAILED'], true)) {
            Log::warning("WAHA Session '{$sessionName}' berstatus {$status}.");
        }
    }

    /**
     * Catat pesan masuk pelanggan ke Histori Tiket aktifnya. Tidak ada balasan otomatis.
     */
    protected function handleIncomingMessage(string $rawPhone, string $messageText): void
    {
        $phone = WhatsappClient::normalizePhoneNumber($rawPhone);
        $messageText = trim($messageText);

        if (empty($phone) || $messageText === '') {
            return;
        }

        // Cari pelanggan terdaftar
        $pelanggan = Pelanggan::query()
            ->where('no_hp', $phone)
            ->orWhere('no_hp', $rawPhone)
            ->orWhere('no_hp', '0'.substr($phone, 2))
            ->first();

        // Jika pelanggan memiliki tiket aktif, catat histori chat ke tiket.
        if ($pelanggan) {
            $activeTicket = Ticket::query()
                ->where('pelanggan_id', $pelanggan->id)
                ->whereNotIn('status', [StatusTicket::Selesai, StatusTicket::Batal])
                ->latest('id')
                ->first();

            if ($activeTicket) {
                TicketHistori::create([
                    'ticket_id' => $activeTicket->id,
                    'oleh_pengguna_id' => $activeTicket->dibuat_oleh,
                    'status_lama' => $activeTicket->status,
                    'status_baru' => $activeTicket->status,
                    'catatan' => "[WhatsApp Pelanggan]: {$messageText}",
                    'is_internal' => false,
                    'created_at' => Carbon::now(),
                ]);
            }
        }
    }

    /**
     * Catat log payload webhook ke database.
     *
     * Diekspos public (bukan protected) karena `WhatsappWebhookController` sekarang mencatat
     * log INI SENDIRI sebelum verifikasi signature -- agar percobaan yang ditolak (signature
     * tidak valid) tetap meninggalkan jejak audit, bukan hanya percobaan yang berhasil.
     *
     * Redelivery event yang sama dilempar sebagai `UniqueConstraintViolationException` (bukan
     * null) agar pemanggil bisa membalas "sudah diterima", bukan 503 yang memicu retry tanpa akhir.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws UniqueConstraintViolationException
     */
    public function logWebhook(array $payload): ?WebhookLog
    {
        try {
            [$provider, $eventType] = $this->detectProviderAndEventType($payload);
            $eventId = $this->deriveProviderEventId($payload);

            return WebhookLog::create([
                'provider' => $provider,
                'provider_event_id' => $eventId,
                'event_type' => $eventType,
                'xendit_event_id' => $eventId,
                'payload' => $payload,
                'status_proses' => StatusWebhookLog::Diterima,
                'diterima_pada' => Carbon::now(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat WebhookLog WhatsApp: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Deteksi provider (gowa/waha/whatsapp) dan event_type dari bentuk payload.
     *
     * `provider` tidak boleh dibiarkan memakai default kolom (`xendit`) -- kolom itu hanya
     * relevan untuk webhook payment gateway, dan sebelumnya seluruh baris WebhookLog WhatsApp
     * salah tercatat sebagai `xendit`, mencemari trail audit & index unik idempotensi.
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: string}
     */
    protected function detectProviderAndEventType(array $payload): array
    {
        if (isset($payload['event'])) {
            $sysblas = $this->resolveGowaSysblas($payload);
            $provider = $sysblas?->provider === SysblasProvider::Gowa ? 'gowa' : 'waha';

            return [$provider, "{$provider}.{$payload['event']}"];
        }

        if (isset($payload['message'])) {
            return ['whatsapp', 'whatsapp.incoming_message'];
        }

        return ['whatsapp', 'whatsapp.tracking'];
    }

    /**
     * Turunkan provider_event_id yang stabil dari payload, dipakai unique index
     * `webhook_log_provider_event_unique` untuk idempotensi.
     *
     * Payload event-driven asli (WAHA/GOWA) tidak selalu punya id di level teratas -- fallback
     * ke id pesan bersarang, diprefiks session + event: satu pesan memicu beberapa event dengan
     * id yang sama (`message`, `message.ack`, `message.revoked`), jadi id saja bentrok. Payload flat
     * legacy tanpa id sama sekali (format lama, masih dipakai gateway produksi) memakai hash
     * ter-bucket per menit sebagai id sintetis -- bukan id sempurna, tapi payload memang tidak
     * menyediakan apa pun yang lebih baik; risiko dedup-palsu dibatasi ke "teks identik, nomor
     * sama, menit yang sama".
     *
     * @param  array<string, mixed>  $payload
     */
    protected function deriveProviderEventId(array $payload): ?string
    {
        if (isset($payload['event'])) {
            if (! empty($payload['id'])) {
                return (string) $payload['id'];
            }

            $session = (string) ($payload['session'] ?? 'default');
            $innerPayload = (array) ($payload['payload'] ?? []);
            $innerId = $innerPayload['id'] ?? null;

            return $innerId ? "{$session}:{$payload['event']}:{$innerId}" : null;
        }

        if (! empty($payload['id'])) {
            return (string) $payload['id'];
        }

        $phone = (string) ($payload['phone'] ?? $payload['sender'] ?? '');
        $text = (string) ($payload['message'] ?? $payload['status'] ?? '');

        if ($phone === '' && $text === '') {
            return null;
        }

        return hash('sha256', "{$phone}|{$text}").':'.Carbon::now()->format('YmdHi');
    }
}
