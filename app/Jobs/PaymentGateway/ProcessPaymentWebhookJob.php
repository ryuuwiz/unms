<?php

namespace App\Jobs\PaymentGateway;

use App\DTO\PaymentGateway\PaymentCallbackData;
use App\Enums\StatusTransaksiGateway;
use App\Enums\StatusWebhookLog;
use App\Models\Invoice;
use App\Models\TransaksiPaymentGateway;
use App\Models\WebhookLog;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPaymentWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * Jumlah percobaan maksimal jika terjadi exception transient.
     */
    public int $tries = 3;

    /**
     * Waktu tunda antar percobaan (detik): 10 detik, 60 detik, 300 detik.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * Hapus job jika model binding sudah tidak ada.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public int $webhookLogId
    ) {
        // Antrean terisolasi khusus pembayaran (lihat supervisor-payments di
        // config/horizon.php) agar pelunasan invoice tidak pernah tertahan di belakang
        // antrean mikrotik-low/wa-blast.
        $this->onQueue('payments');
    }

    /**
     * Eksekusi pemrosesan webhook di antrean latar belakang secara idempoten.
     */
    public function handle(PaymentGatewayManager $manager): void
    {
        /** @var WebhookLog|null $webhookLog */
        $webhookLog = WebhookLog::find($this->webhookLogId);

        if (! $webhookLog) {
            Log::warning("ProcessPaymentWebhookJob diabaikan: WebhookLog ID {$this->webhookLogId} tidak ditemukan.");

            return;
        }

        // Idempotensi awal: Jika log sudah diproses sebelumnya, hindari eksekusi ulang
        if ($webhookLog->status_proses === StatusWebhookLog::Diproses) {
            Log::info("ProcessPaymentWebhookJob diabaikan: WebhookLog ID {$this->webhookLogId} sudah berstatus diproses.");

            return;
        }

        $provider = strtolower(trim($webhookLog->provider ?: 'xendit'));
        $payload = (array) ($webhookLog->payload ?? []);

        try {
            $driver = $manager->driver($provider);
        } catch (Throwable $e) {
            $webhookLog->update([
                'status_proses' => StatusWebhookLog::Gagal,
                'catatan_error' => "Driver gateway [{$provider}] tidak ditemukan: ".$e->getMessage(),
            ]);

            return;
        }

        // 1. Normalisasi Payload Callback via Request DTO
        $simulatedRequest = Request::create(
            uri: "/webhook/payment/{$provider}",
            method: 'POST',
            parameters: $payload,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload) ?: '{}'
        );

        /** @var PaymentCallbackData $callbackData */
        $callbackData = $driver->parseWebhookPayload($simulatedRequest);

        // Pastikan provider_event_id tersinkron di webhook_log
        if (! empty($callbackData->eventId) && empty($webhookLog->provider_event_id)) {
            $webhookLog->update([
                'provider_event_id' => $callbackData->eventId,
                'xendit_event_id' => $callbackData->eventId,
            ]);
        }

        // 2. Cari Transaksi Payment Gateway / Invoice terkait
        /** @var TransaksiPaymentGateway|null $transaksi */
        $transaksi = null;

        if (! empty($callbackData->externalId)) {
            $transaksi = TransaksiPaymentGateway::where('external_id', $callbackData->externalId)->first();
        }

        if (! $transaksi && ! empty($callbackData->eventId)) {
            $transaksi = TransaksiPaymentGateway::where('provider_reference_id', $callbackData->eventId)
                ->orWhere('xendit_reference_id', $callbackData->eventId)
                ->first();
        }

        /** @var Invoice|null $invoice */
        $invoice = $transaksi ? $transaksi->invoice : null;

        if (! $invoice && ! empty($callbackData->eventId)) {
            $invoice = Invoice::where('payment_gateway_id', $callbackData->eventId)
                ->orWhere('xendit_invoice_id', $callbackData->eventId)
                ->first();
        }

        if (! $invoice && ! empty($callbackData->externalId)) {
            if (is_numeric($callbackData->externalId)) {
                $invoice = Invoice::find((int) $callbackData->externalId);
            } else {
                $invoice = Invoice::where('no_invoice', $callbackData->externalId)->first();
                if (! $invoice && preg_match('/(INV-[\w-]+)/', $callbackData->externalId, $matches)) {
                    $invoice = Invoice::where('no_invoice', $matches[1])->first();
                }
            }
        }

        if (! $invoice) {
            if ($callbackData->isTest) {
                $webhookLog->update([
                    'status_proses' => StatusWebhookLog::Diproses,
                    'catatan_error' => "Simulasi uji coba webhook dari Dashboard {$provider} berhasil diproses.",
                ]);

                Log::info("Test Webhook ({$provider}) berhasil diproses di antrean.", [
                    'external_id' => $callbackData->externalId,
                    'event_id' => $callbackData->eventId,
                ]);

                return;
            }

            $webhookLog->update([
                'status_proses' => StatusWebhookLog::Diabaikan,
                'catatan_error' => "Tagihan/Transaksi dengan identifier [{$callbackData->externalId} / {$callbackData->eventId}] tidak ditemukan.",
            ]);

            Log::warning("Webhook {$provider} diabaikan oleh queue: Target [{$callbackData->externalId}] tidak ditemukan.");

            return;
        }

        if ($transaksi && $webhookLog->transaksi_payment_gateway_id !== $transaksi->id) {
            $webhookLog->update(['transaksi_payment_gateway_id' => $transaksi->id]);
        }

        // 3. Eksekusi Berdasarkan Status Callback
        if ($callbackData->isPaid()) {
            // Validasi Ketat Mata Uang (ADR 0028 §2): hanya IDR yang diterima.
            // Payload lama yang tidak menyertakan currency dianggap IDR (default gateway lokal).
            if (! empty($callbackData->currency) && strtoupper($callbackData->currency) !== 'IDR') {
                $catatan = "Anomali mata uang pembayaran {$provider}: Diterima {$callbackData->currency}, seharusnya IDR";
                $webhookLog->update([
                    'status_proses' => StatusWebhookLog::Gagal,
                    'catatan_error' => $catatan,
                ]);

                Log::error("{$catatan} untuk Invoice {$invoice->no_invoice}", [
                    'invoice_id' => $invoice->id,
                    'currency' => $callbackData->currency,
                    'external_id' => $callbackData->externalId,
                ]);

                return;
            }

            // Validasi Ketat Integritas Nominal (Strict Integer Amount Match).
            // Tidak ada toleransi untuk underpayment maupun overpayment, termasuk nominal 0 --
            // callback yang melaporkan 0 rupiah dibayar bukan pengecualian, itu adalah anomali.
            $expectedAmount = (int) round($transaksi ? (float) $transaksi->total_tagihan : (float) $invoice->jumlah_setelah_promo);
            $actualAmount = (int) round($callbackData->paidAmount);

            if ($actualAmount !== $expectedAmount) {
                $catatan = "Anomali nominal pembayaran {$provider}: Diterima Rp ".number_format($actualAmount, 0, ',', '.').', seharusnya Rp '.number_format($expectedAmount, 0, ',', '.');
                $webhookLog->update([
                    'status_proses' => StatusWebhookLog::Gagal,
                    'catatan_error' => $catatan,
                ]);

                Log::error("{$catatan} untuk Invoice {$invoice->no_invoice}", [
                    'invoice_id' => $invoice->id,
                    'actual_amount' => $actualAmount,
                    'expected_amount' => $expectedAmount,
                    'external_id' => $callbackData->externalId,
                ]);

                return;
            }

            // Eksekusi Pelunasan dengan Row Locking & Event Dispatching
            $manager->prosesPelunasan(
                invoice: $invoice,
                payloadOrData: $callbackData,
                transaksi: $transaksi,
                webhookLog: $webhookLog
            );

            Log::info("Pelunasan invoice {$invoice->no_invoice} berhasil diproses via antrean {$provider}", [
                'invoice_id' => $invoice->id,
                'external_id' => $callbackData->externalId,
                'webhook_log_id' => $webhookLog->id,
            ]);

            return;
        }

        if ($callbackData->isExpired() || $callbackData->isFailed()) {
            DB::transaction(function () use ($invoice, $transaksi, $callbackData, $webhookLog) {
                /** @var Invoice $lockedInvoice */
                $lockedInvoice = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

                if (! $lockedInvoice->isLunas()) {
                    $lockedInvoice->update([
                        'payment_gateway_url' => null,
                        'payment_gateway_status' => 'EXPIRED',
                        'xendit_invoice_url' => null,
                        'xendit_status' => 'EXPIRED',
                    ]);
                }

                if ($transaksi && $transaksi->status === StatusTransaksiGateway::Pending) {
                    $transaksi->update([
                        'status' => StatusTransaksiGateway::Expired,
                        'payload_response' => $callbackData->rawPayload,
                    ]);
                }

                $webhookLog->update(['status_proses' => StatusWebhookLog::Diproses]);
            });

            return;
        }

        // Event status lainnya (misal PENDING)
        $webhookLog->update(['status_proses' => StatusWebhookLog::Diproses]);
    }

    /**
     * Penanganan jika antrean gagal total setelah mencapai batas maksimal percobaan.
     *
     * Melaporkan exception ke Sentry secara eksplisit (bukan hanya Log::critical) karena
     * Horizon dashboard sengaja dikunci di lingkungan ini (viewHorizon gate kosong) dan
     * log channel default tidak terhubung ke Sentry (config/sentry.php enable_logs=false).
     * Tanpa ini, kegagalan total webhook pembayaran tidak akan pernah terlihat siapa pun --
     * lihat juga RekonsiliasiPembayaranCommand sebagai lapisan pemulihan keduanya.
     */
    public function failed(?Throwable $exception): void
    {
        Log::critical("ProcessPaymentWebhookJob GAGAL TOTAL untuk WebhookLog ID {$this->webhookLogId}: ".$exception?->getMessage(), [
            'exception' => $exception,
        ]);

        if ($exception) {
            \Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
                $scope->setContext('payment_webhook', [
                    'webhook_log_id' => $this->webhookLogId,
                ]);
            });
            \Sentry\captureException($exception);
        }

        /** @var WebhookLog|null $webhookLog */
        $webhookLog = WebhookLog::find($this->webhookLogId);

        if ($webhookLog) {
            $webhookLog->update([
                'status_proses' => StatusWebhookLog::Gagal,
                'catatan_error' => 'Job antrean gagal dieksekusi setelah 3x percobaan: '.$exception?->getMessage(),
            ]);
        }
    }
}
