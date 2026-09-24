<?php

namespace App\Services\Whatsapp;

use App\Enums\StatusInvoice;
use App\Enums\StatusWebhookLog;
use App\Enums\Sysblas\SysblasProvider;
use App\Enums\Ticket\StatusTicket;
use App\Enums\Wa\StatusAntrianWa;
use App\Models\AntrianWaBlast;
use App\Models\Invoice;
use App\Models\Pelanggan;
use App\Models\Sysblas;
use App\Models\Ticket;
use App\Models\TicketHistori;
use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Sentry\State\Scope;

class WhatsappWebhookService
{
    public function __construct(
        protected WhatsappService $whatsappService
    ) {}

    /**
     * Proses payload webhook WhatsApp secara sinkron: mencatat WebhookLog lalu langsung
     * menanganinya. Dipakai oleh `whatsapp:simulate-webhook` (CLI lokal, bukan HTTP publik,
     * sehingga tidak perlu antrean). Jalur HTTP publik (`WhatsappWebhookController`) mencatat
     * WebhookLog sendiri lebih dulu (agar percobaan yang ditolak signature tetap tercatat),
     * lalu memproses via `ProcessWhatsappWebhookJob` di antrean -- lihat `handlePayload()`.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: bool, type: string, message: string}
     */
    public function process(array $payload): array
    {
        return $this->handlePayload($payload, $this->logWebhook($payload));
    }

    /**
     * Tangani payload webhook WhatsApp yang WebhookLog-nya sudah dicatat sebelumnya.
     *
     * Mendukung format event-driven `{event, session, payload}` (dipakai WAHA maupun GOWA —
     * keduanya berbasis library whatsmeow dan memakai konvensi nama event yang sama seperti
     * `message`/`message.ack`) serta format flat legacy `{phone, message}` / `{phone, status}`
     * (dipakai perintah `whatsapp:simulate-webhook` untuk pengujian lokal, dan masih dipakai
     * oleh pengirim gateway lama di produksi).
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: bool, type: string, message: string}
     */
    public function handlePayload(array $payload, ?WebhookLog $log): array
    {
        try {
            // 1. Deteksi format event WAHA/GOWA (event-driven)
            if (isset($payload['event']) && isset($payload['payload'])) {
                $result = $this->handleWahaEvent($payload);
                $log?->update(['status_proses' => StatusWebhookLog::Diproses]);

                return $result;
            }

            // 2. Deteksi format flat legacy: Inbound chat / Pesan masuk
            if (isset($payload['message']) && (isset($payload['phone']) || isset($payload['sender']))) {
                $reply = $this->handleIncomingMessage([
                    'phone' => $payload['phone'] ?? $payload['sender'] ?? '',
                    'message' => $payload['message'],
                    'raw' => $payload,
                ]);
                $log?->update(['status_proses' => StatusWebhookLog::Diproses]);

                return [
                    'status' => true,
                    'type' => 'incoming_message',
                    'message' => $reply ? 'Pesan masuk diproses dan dibalas otomatis.' : 'Pesan masuk berhasil dicatat.',
                ];
            }

            // 3. Deteksi format flat legacy: Tracking status pengiriman (DLR)
            if (isset($payload['status']) && (isset($payload['phone']) || isset($payload['id']))) {
                $this->handleTrackingStatus($payload);
                $log?->update(['status_proses' => StatusWebhookLog::Diproses]);

                return [
                    'status' => true,
                    'type' => 'tracking_status',
                    'message' => 'Status pengiriman pesan berhasil diperbarui.',
                ];
            }

            $log?->update(['status_proses' => StatusWebhookLog::Diabaikan]);

            return [
                'status' => true,
                'type' => 'unknown',
                'message' => 'Payload diakui namun tidak memerlukan tindakan.',
            ];
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
     * (disimpan pada kolom `api_secret`). Jika koneksi tidak memiliki secret terkonfigurasi,
     * verifikasi dilewati (tidak ada apa pun untuk dicocokkan).
     *
     * Catatan: nama header signature GOWA belum terdokumentasi di openapi.yaml (hanya field
     * registrasi `webhook_secret` yang tercatat) — nama header di bawah ini perlu dikonfirmasi
     * ulang terhadap instance GOWA yang benar-benar berjalan dan disesuaikan bila berbeda.
     */
    public function verifyGowaSignature(Request $request, Sysblas $sysblas): bool
    {
        $secret = (string) ($sysblas->api_secret ?? '');
        if ($secret === '') {
            return true;
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
     * Tangani event terstruktur dari WAHA API.
     *
     * @param  array<string, mixed>  $data
     * @return array{status: bool, type: string, message: string}
     */
    protected function handleWahaEvent(array $data): array
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

                $reply = $this->handleIncomingMessage([
                    'phone' => $rawPhone,
                    'message' => $body,
                    'session' => $session,
                    'raw' => $data,
                ]);

                return [
                    'status' => true,
                    'type' => 'incoming_message',
                    'message' => $reply ? 'Pesan masuk WAHA diproses dan dibalas.' : 'Pesan masuk WAHA dicatat.',
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
     * Update status pengiriman pada AntrianWaBlast berdasarkan tracking DLR WABLAS legacy.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleTrackingStatus(array $payload): void
    {
        $phone = WhatsappClient::normalizePhoneNumber($payload['phone'] ?? $payload['sender'] ?? null);
        $statusStr = strtolower((string) ($payload['status'] ?? ''));
        $note = (string) ($payload['note'] ?? $payload['message'] ?? '');

        $query = AntrianWaBlast::query()->latest('id');

        if ($phone) {
            $query->where('no_hp_tujuan', $phone);
        }

        $antrian = $query->where('created_at', '>=', Carbon::now()->subDays(3))->first();

        if (! $antrian) {
            return;
        }

        $existingLog = (array) ($antrian->response_log ?? []);
        $existingLog['tracking_webhook'] = $payload;

        if (in_array($statusStr, ['sent', 'delivered', 'read', 'success'], true)) {
            $antrian->update([
                'status' => StatusAntrianWa::Terkirim,
                'dikirim_pada' => $antrian->dikirim_pada ?? Carbon::now(),
                'response_log' => $existingLog,
                'pesan_error' => null,
            ]);
        } elseif (in_array($statusStr, ['failed', 'rejected', 'error'], true)) {
            $antrian->update([
                'status' => StatusAntrianWa::Gagal,
                'pesan_error' => $note ?: "Gagal terkirim ({$statusStr})",
                'response_log' => $existingLog,
            ]);
        }
    }

    /**
     * Tangani pesan masuk dari pelanggan (Inbound Chat).
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleIncomingMessage(array $payload): ?string
    {
        $rawPhone = (string) ($payload['phone'] ?? $payload['sender'] ?? '');
        $phone = WhatsappClient::normalizePhoneNumber($rawPhone);
        $messageText = trim((string) ($payload['message'] ?? ''));

        if (empty($phone) || empty($messageText)) {
            return null;
        }

        // Cari pelanggan terdaftar
        $pelanggan = Pelanggan::query()
            ->where('no_hp', $phone)
            ->orWhere('no_hp', $rawPhone)
            ->orWhere('no_hp', '0'.substr($phone, 2))
            ->first();

        // 1. Jika ada kata kunci interaktif, buat balasan otomatis
        $replyMessage = $this->generateInteractiveReply($messageText, $pelanggan);

        // 2. Jika pelanggan memiliki tiket aktif, catat histori chat ke tiket
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

        // Kirim auto-reply jika ada pesan balasan
        if ($replyMessage) {
            $this->whatsappService->antrikanPesanKustom(
                noHp: $phone,
                pesan: $replyMessage,
                referensi: $pelanggan,
                jenis: 'webhook_autoreply'
            );
        }

        return $replyMessage;
    }

    /**
     * Buat balasan interaktif berdasarkan kata kunci pesan.
     */
    protected function generateInteractiveReply(string $text, ?Pelanggan $pelanggan): ?string
    {
        $upper = strtoupper(trim($text));

        // Keyword TAGIHAN / INFO TAGIHAN / CEK TAGIHAN / BAYAR
        if (str_contains($upper, 'TAGIHAN') || str_contains($upper, 'BAYAR') || str_contains($upper, 'INVOICE')) {
            if (! $pelanggan) {
                return 'Halo! Nomor WhatsApp Anda belum terdaftar sebagai pelanggan kami. Silahkan hubungi Customer Service untuk informasi pendaftaran layanan.';
            }

            $unpaidInvoices = Invoice::query()
                ->where('pelanggan_id', $pelanggan->id)
                ->where('status', StatusInvoice::MenungguPembayaran)
                ->orderBy('tanggal_jatuh_tempo')
                ->get();

            if ($unpaidInvoices->isEmpty()) {
                return "Halo Bapak/Ibu *{$pelanggan->namaLengkap()}*,\n\nSaat ini Anda *tidak memiliki tagihan tertunggak* (Semua tagihan lunas). Terima kasih atas kelancaran pembayaran Anda! 🙏";
            }

            $totalNominal = $unpaidInvoices->sum('jumlah');
            $msg = "Halo Bapak/Ibu *{$pelanggan->namaLengkap()}* (No Reg: {$pelanggan->no_reg}),\n\nBerikut rincian tagihan Anda yang belum dibayar:\n";

            foreach ($unpaidInvoices as $inv) {
                $tglJatuhTempo = $inv->tanggal_jatuh_tempo->format('d/m/Y');
                $nominal = number_format($inv->jumlah_setelah_promo ?? $inv->jumlah, 0, ',', '.');
                $linkBayar = $inv->xendit_invoice_url ?? route('portal.invoice.show', $inv->id);
                $msg .= "• *{$inv->no_invoice}* : Rp {$nominal} (Jatuh Tempo: {$tglJatuhTempo})\n  Link Bayar: {$linkBayar}\n";
            }

            $msg .= "\n*Total Tagihan:* Rp ".number_format($totalNominal, 0, ',', '.')."\n\nSilahkan lakukan pembayaran melalui link pembayaran di atas atau via Portal Pelanggan.";

            return $msg;
        }

        // Keyword TIKET / GANGGUAN / KENDALA
        if (str_contains($upper, 'TIKET') || str_contains($upper, 'STATUS TIKET') || str_contains($upper, 'GANGGUAN') || str_contains($upper, 'RUSAK')) {
            if (! $pelanggan) {
                return 'Halo! Untuk pelaporan kendala atau tiket gangguan, mohon sebutkan No. Registrasi Pelanggan atau hubungi Helpdesk kami.';
            }

            $activeTickets = Ticket::query()
                ->where('pelanggan_id', $pelanggan->id)
                ->whereNotIn('status', [StatusTicket::Selesai, StatusTicket::Batal])
                ->latest('id')
                ->get();

            if ($activeTickets->isEmpty()) {
                return "Halo Bapak/Ibu *{$pelanggan->namaLengkap()}*,\n\nSaat ini *tidak ada tiket gangguan aktif* untuk layanan Anda. Jika mengalami kendala koneksi, silahkan sampaikan detail kendala Anda di sini agar tim teknisi kami segera menindaklanjuti.";
            }

            $msg = "Halo Bapak/Ibu *{$pelanggan->namaLengkap()}*,\n\nStatus tiket penanganan Anda saat ini:\n";
            foreach ($activeTickets as $t) {
                $statusLabel = $t->status->label();
                $msg .= "• *{$t->nomor_ticket}* - {$t->jenis->label()} (Status: *{$statusLabel}*)\n";
            }
            $msg .= "\nTim teknisi kami sedang memproses kendala Anda. Mohon ditunggu.";

            return $msg;
        }

        // Keyword MENU / BANTUAN / INFO
        if ($upper === 'MENU' || $upper === 'BANTUAN' || $upper === 'INFO' || $upper === 'HELP') {
            $nama = $pelanggan ? $pelanggan->namaLengkap() : 'Pelanggan';

            return "Halo *{$nama}*,\n\nSelamat datang di Layanan Otomatis WhatsApp.\nKetik kata kunci berikut untuk info cepat:\n\n1. *TAGIHAN* - Untuk cek tagihan & link pembayaran\n2. *TIKET* - Untuk cek status penanganan kendala\n3. *BANTUAN* - Untuk panduan bantuan\n\nUntuk berbicara langsung dengan Customer Service, silahkan tinggalkan pesan Anda di sini.";
        }

        return null;
    }

    /**
     * Catat log payload webhook ke database.
     *
     * Diekspos public (bukan protected) karena `WhatsappWebhookController` sekarang mencatat
     * log INI SENDIRI sebelum verifikasi signature -- agar percobaan yang ditolak (signature
     * tidak valid) tetap meninggalkan jejak audit, bukan hanya percobaan yang berhasil.
     *
     * @param  array<string, mixed>  $payload
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
     * ke id pesan bersarang (diprefiks session agar tidak bentrok lintas session). Payload flat
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

            return $innerId ? "{$session}:{$innerId}" : null;
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
