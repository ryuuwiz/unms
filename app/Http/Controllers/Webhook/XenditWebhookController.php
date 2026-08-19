<?php

namespace App\Http\Controllers\Webhook;

use App\DTO\Xendit\XenditCallbackData;
use App\Enums\MasaAktifSatuan;
use App\Enums\MetodePembayaran;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\StatusTransaksiGateway;
use App\Enums\StatusWebhookLog;
use App\Events\InvoicePaidEvent;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Pembayaran;
use App\Models\TransaksiPaymentGateway;
use App\Models\WebhookLog;
use App\Services\Xendit\XenditWebhookVerifier;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class XenditWebhookController extends Controller
{
    public function __construct(
        protected XenditWebhookVerifier $verifier
    ) {}

    /**
     * Handle incoming webhook callbacks from Xendit.
     */
    public function handle(Request $request): JsonResponse
    {
        // 1. Verifikasi Signature / Header Token
        if (! $this->verifier->verifikasi($request)) {
            Log::warning('Webhook Xendit ditolak: Callback Token tidak valid.', [
                'ip' => $request->ip(),
                'headers' => $request->headers->all(),
            ]);

            return response()->json(['message' => 'Unauthorized: Invalid callback token'], 401);
        }

        $payload = $request->all();
        $callbackData = XenditCallbackData::fromArray($payload);

        // 2. Cek Idempotensi Webhook Event ID
        if (! empty($callbackData->eventId)) {
            $alreadyProcessed = WebhookLog::where('xendit_event_id', $callbackData->eventId)
                ->where('status_proses', StatusWebhookLog::Diproses)
                ->exists();

            if ($alreadyProcessed) {
                return response()->json([
                    'message' => 'Webhook already processed',
                    'event_id' => $callbackData->eventId,
                ], 200);
            }
        }

        // 3. Catat Webhook Log
        $webhookLog = WebhookLog::create([
            'event_type' => $callbackData->eventType,
            'xendit_event_id' => $callbackData->eventId,
            'payload' => $payload,
            'status_proses' => StatusWebhookLog::Diterima,
            'diterima_pada' => Carbon::now(),
        ]);

        // 4. Cari Transaksi Payment Gateway berdasarkan external_id
        /** @var TransaksiPaymentGateway|null $transaksi */
        $transaksi = TransaksiPaymentGateway::where('external_id', $callbackData->externalId)->first();

        if (! $transaksi) {
            $webhookLog->update([
                'status_proses' => StatusWebhookLog::Diabaikan,
                'catatan_error' => "Transaksi dengan external_id [{$callbackData->externalId}] tidak ditemukan.",
            ]);

            Log::warning("Webhook Xendit diabaikan: Transaksi [{$callbackData->externalId}] tidak ditemukan.");

            return response()->json([
                'message' => 'Transaction not found, ignored',
                'external_id' => $callbackData->externalId,
            ], 200);
        }

        $webhookLog->update(['transaksi_payment_gateway_id' => $transaksi->id]);

        // 5. Eksekusi Transaksi Database Terisolasi dengan Row Locking
        try {
            $eventToDispatch = null;

            DB::transaction(function () use ($transaksi, $callbackData, $webhookLog, &$eventToDispatch) {
                /** @var Invoice $lockedInvoice */
                $lockedInvoice = Invoice::where('id', $transaksi->invoice_id)->lockForUpdate()->firstOrFail();

                // Guard Clause Idempotensi: Jika invoice sudah lunas sebelumnya
                if ($lockedInvoice->isLunas()) {
                    $transaksi->update([
                        'status' => StatusTransaksiGateway::Paid,
                        'payload_response' => $callbackData->rawPayload,
                    ]);
                    $webhookLog->update(['status_proses' => StatusWebhookLog::Diproses]);

                    return;
                }

                $dibayarPada = $callbackData->paidAt ? Carbon::parse($callbackData->paidAt) : Carbon::now();

                // A. Update Transaksi Payment Gateway
                $transaksi->update([
                    'status' => StatusTransaksiGateway::Paid,
                    'payload_response' => $callbackData->rawPayload,
                ]);

                // B. Buat Record Pembayaran
                $channelDetailText = $transaksi->channel_detail ? strtoupper($transaksi->channel_detail) : '';
                $pembayaran = Pembayaran::create([
                    'invoice_id' => $lockedInvoice->id,
                    'metode' => MetodePembayaran::PaymentGateway,
                    'referensi_transaksi' => $callbackData->paymentReference ?: $transaksi->external_id,
                    'jumlah_dibayar' => $callbackData->amount > 0 ? $callbackData->amount : (float) $transaksi->total_tagihan,
                    'dibayar_pada' => $dibayarPada,
                    'catatan' => "Pembayaran otomatis Xendit {$transaksi->channel->label()} {$channelDetailText}",
                ]);

                // C. Update Status Invoice
                $lockedInvoice->update([
                    'status' => StatusInvoice::Lunas,
                    'tanggal_lunas' => $dibayarPada->toDateString(),
                    'metode_pembayaran' => MetodePembayaran::PaymentGateway,
                ]);

                // D. Perpanjang Masa Aktif Layanan Pelanggan (ADR 0004 & PRD 4.2)
                $layanan = $lockedInvoice->layananPelanggan()->lockForUpdate()->first();
                if ($layanan) {
                    $paket = $layanan->paketLayanan;
                    $masaNilai = (int) $paket->masa_aktif_nilai;
                    $masaSatuan = $paket->masa_aktif_satuan;

                    $promo = $lockedInvoice->promo;
                    $bonusBulan = ($promo && $promo->bonus_bulan) ? (int) $promo->bonus_bulan : 0;

                    $currentExpired = $layanan->tanggal_expired ? Carbon::parse($layanan->tanggal_expired) : null;
                    $baseDate = ($currentExpired && $currentExpired->isFuture())
                        ? $currentExpired->copy()
                        : $dibayarPada->copy()->startOfDay();

                    if ($masaSatuan === MasaAktifSatuan::Bulan) {
                        $newExpired = $baseDate->addMonths($masaNilai + $bonusBulan);
                    } else {
                        $newExpired = $baseDate->addDays($masaNilai);
                    }

                    $layanan->update([
                        'tanggal_expired' => $newExpired->toDateString(),
                        'status' => StatusLayanan::Aktif,
                    ]);
                }

                // E. Update Status Log Webhook
                $webhookLog->update(['status_proses' => StatusWebhookLog::Diproses]);

                // Siapkan event untuk dipancarkan setelah DB commit
                $eventToDispatch = new InvoicePaidEvent($lockedInvoice, $pembayaran);
            });

            // 6. Pancarkan Event Post-Commit
            if ($eventToDispatch) {
                event($eventToDispatch);
            }

            return response()->json([
                'message' => 'Payment processed successfully',
                'external_id' => $callbackData->externalId,
            ], 200);
        } catch (Exception $e) {
            $webhookLog->update([
                'status_proses' => StatusWebhookLog::Gagal,
                'catatan_error' => $e->getMessage(),
            ]);

            Log::error("Gagal memproses webhook Xendit [{$callbackData->externalId}]: ".$e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json(['message' => 'Internal processing error'], 500);
        }
    }
}
