<?php

namespace App\Services\Wablas;

use App\Enums\StatusInvoice;
use App\Enums\StatusWebhookLog;
use App\Enums\Ticket\StatusTicket;
use App\Enums\Wa\StatusAntrianWa;
use App\Models\AntrianWaBlast;
use App\Models\Invoice;
use App\Models\Pelanggan;
use App\Models\Ticket;
use App\Models\TicketHistori;
use App\Models\WebhookLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class WablasWebhookService
{
    public function __construct(
        protected WablasService $wablasService
    ) {}

    /**
     * Proses payload webhook WABLAS (Tracking status atau Pesan Masuk).
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: bool, type: string, message: string}
     */
    public function process(array $payload): array
    {
        $log = $this->logWebhook($payload);

        try {
            // Deteksi tipe webhook berdasarkan struktur payload
            if (isset($payload['message']) && (isset($payload['phone']) || isset($payload['sender']))) {
                // Inbound chat / Pesan masuk
                $reply = $this->handleIncomingMessage($payload);
                $log?->update(['status_proses' => StatusWebhookLog::Diproses]);

                return [
                    'status' => true,
                    'type' => 'incoming_message',
                    'message' => $reply ? 'Pesan masuk diproses dan dibalas otomatis.' : 'Pesan masuk berhasil dicatat.',
                ];
            }

            if (isset($payload['status']) && (isset($payload['phone']) || isset($payload['id']))) {
                // Tracking status pengiriman (DLR)
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

            Log::error('WABLAS Webhook Processing Error: '.$e->getMessage(), ['payload' => $payload]);

            return [
                'status' => false,
                'type' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update status pengiriman pada AntrianWaBlast berdasarkan tracking DLR WABLAS.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleTrackingStatus(array $payload): void
    {
        $phone = WablasClient::normalizePhoneNumber($payload['phone'] ?? $payload['sender'] ?? null);
        $statusStr = strtolower((string) ($payload['status'] ?? ''));
        $note = (string) ($payload['note'] ?? $payload['message'] ?? '');
        $messageId = $payload['id'] ?? null;

        $query = AntrianWaBlast::query()->latest('id');

        if ($phone) {
            $query->where('no_hp_tujuan', $phone);
        }

        // Cari antrean dalam 3 hari terakhir yang relevan
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
        $phone = WablasClient::normalizePhoneNumber($rawPhone);
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

        // 1. Jika ada kata kunci interaktif, balas secara otomatis
        $replyMessage = $this->generateInteractiveReply($messageText, $pelanggan);

        // 2. Jika pelanggan memiliki tiket aktif (bukan Selesai/Dibatalkan), catat histori chat ke tiket
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
            $this->wablasService->antrikanPesanKustom(
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

        // Keyword TAGIHAN / INFO TAGIHAN / CEK TAGIHAN
        if (str_contains($upper, 'TAGIHAN') || str_contains($upper, 'BAYAR') || str_contains($upper, 'INVOICE')) {
            if (! $pelanggan) {
                return 'Halo! Nomor WhatsApp Anda belum terdaftar sebagai pelanggan kami. Silahkan hubungi Customer Service untuk informasi layanan.';
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
                $tglJatuhTempo = $inv->tanggal_jatuh_tempo?->format('d/m/Y') ?? '-';
                $nominal = number_format($inv->jumlah, 0, ',', '.');
                $msg .= "• *{$inv->no_invoice}* : Rp {$nominal} (Jatuh Tempo: {$tglJatuhTempo})\n";
            }

            $msg .= "\n*Total Tagihan:* Rp ".number_format($totalNominal, 0, ',', '.')."\n\nSilahkan lakukan pembayaran melalui portal pelanggan atau link pembayaran yang telah kami kirimkan.";

            return $msg;
        }

        // Keyword TIKET / GANGGUAN / KENDALA
        if (str_contains($upper, 'TIKET') || str_contains($upper, 'STATUS TIKET') || str_contains($upper, 'GANGGUAN') || str_contains($upper, 'RUSAK')) {
            if (! $pelanggan) {
                return 'Halo! Untuk pelaporan kendala atau tiket gangguan, mohon sebutkan ID Pelanggan atau hubungi Helpdesk kami.';
            }

            $activeTickets = Ticket::query()
                ->where('pelanggan_id', $pelanggan->id)
                ->whereNotIn('status', [StatusTicket::Selesai, StatusTicket::Batal])
                ->latest('id')
                ->get();

            if ($activeTickets->isEmpty()) {
                return "Halo Bapak/Ibu *{$pelanggan->namaLengkap()}*,\n\nSaat ini *tidak ada tiket gangguan aktif* untuk layanan Anda. Jika mengalami kendala koneksi, silahkan sampaikan detail kendala Anda di sini agar tim teknisi kami dapat segera menindaklanjuti.";
            }

            $msg = "Halo Bapak/Ibu *{$pelanggan->namaLengkap()}*,\n\nStatus tiket penanganan Anda saat ini:\n";
            foreach ($activeTickets as $t) {
                $statusLabel = $t->status->label();
                $msg .= "• *{$t->nomor_ticket}* - {$t->jenis->label()} (Status: *{$statusLabel}*)\n";
                if ($t->catatan_penyelesaian) {
                    $msg .= "  Catatan: {$t->catatan_penyelesaian}\n";
                }
            }
            $msg .= "\nTim teknisi kami sedang memproses kendala Anda. Mohon ditunggu.";

            return $msg;
        }

        // Keyword MENU / BANTUAN / INFO
        if ($upper === 'MENU' || $upper === 'BANTUAN' || $upper === 'INFO' || $upper === 'HELP') {
            $nama = $pelanggan ? $pelanggan->namaLengkap() : 'Pelanggan';

            return "Halo *{$nama}*,\n\nSelamat datang di Layanan Otomatis WhatsApp.\nKetik kata kunci berikut untuk info cepat:\n\n1. *TAGIHAN* - Untuk cek tagihan & status pembayaran\n2. *TIKET* - Untuk cek status penanganan kendala\n3. *BANTUAN* - Untuk panduan bantuan\n\nUntuk berbicara langsung dengan Customer Service, silahkan tinggalkan pesan Anda.";
        }

        return null;
    }

    /**
     * Catat log payload webhook ke database.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function logWebhook(array $payload): ?WebhookLog
    {
        try {
            return WebhookLog::create([
                'event_type' => isset($payload['message']) ? 'wablas.incoming_message' : 'wablas.tracking',
                'xendit_event_id' => $payload['id'] ?? null,
                'payload' => $payload,
                'status_proses' => StatusWebhookLog::Diterima,
                'diterima_pada' => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat WebhookLog WABLAS: '.$e->getMessage());

            return null;
        }
    }
}
