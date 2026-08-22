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
        // 1. Verifikasi Signature / Header Token (backup check selain middleware)
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

        // 4. Cari Transaksi Payment Gateway / Invoice terkait
        /** @var TransaksiPaymentGateway|null $transaksi */
        $transaksi = null;
        if (! empty($callbackData->externalId)) {
            $transaksi = TransaksiPaymentGateway::where('external_id', $callbackData->externalId)->first();
        }

        if (! $transaksi && ! empty($callbackData->eventId)) {
            $transaksi = TransaksiPaymentGateway::where('xendit_reference_id', $callbackData->eventId)->first();
        }

        /** @var Invoice|null $invoice */
        $invoice = $transaksi ? $transaksi->invoice : null;

        if (! $invoice && ! empty($callbackData->eventId)) {
            $invoice = Invoice::where('xendit_invoice_id', $callbackData->eventId)->first();
        }

        if (! $invoice && ! empty($callbackData->externalId)) {
            // Cek jika externalId mengandung nomor invoice (misal: INV-INV-202608-000001-...)
            $invoice = Invoice::where('no_invoice', $callbackData->externalId)->first();
        }

        if (! $invoice) {
            $webhookLog->update([
                'status_proses' => StatusWebhookLog::Diabaikan,
                'catatan_error' => "Tagihan/Transaksi dengan identifier [{$callbackData->externalId} / {$callbackData->eventId}] tidak ditemukan.",
            ]);

            Log::warning("Webhook Xendit diabaikan: Target [{$callbackData->externalId}] tidak ditemukan.");

            return response()->json([
                'message' => 'Target invoice or transaction not found, ignored',
                'external_id' => $callbackData->externalId,
            ], 200);
        }

        if ($transaksi) {
            $webhookLog->update(['transaksi_payment_gateway_id' => $transaksi->id]);
        }

        $isPaidStatus = in_array(strtoupper($callbackData->status), ['PAID', 'SETTLED', 'SUCCEEDED'], true);
        $isExpiredStatus = strtoupper($callbackData->status) === 'EXPIRED';

        // 5. Eksekusi Transaksi Database Terisolasi dengan Row Locking
        try {
            $eventToDispatch = null;

            DB::transaction(function () use ($invoice, $transaksi, $callbackData, $webhookLog, $isPaidStatus, $isExpiredStatus, &$eventToDispatch) {
                /** @var Invoice $lockedInvoice */
                $lockedInvoice = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

                // ─── Kasus A: Pembayaran Lunas (PAID / SETTLED) ─────────────
                if ($isPaidStatus) {
                    // Guard Clause Idempotensi: Jika invoice sudah lunas sebelumnya
                    if ($lockedInvoice->isLunas()) {
                        if ($transaksi) {
                            $transaksi->update([
                                'status' => StatusTransaksiGateway::Paid,
                                'payload_response' => $callbackData->rawPayload,
                            ]);
                        }
                        $lockedInvoice->update(['xendit_status' => 'PAID']);
                        $webhookLog->update(['status_proses' => StatusWebhookLog::Diproses]);

                        return;
                    }

                    $dibayarPada = $callbackData->paidAt ? Carbon::parse($callbackData->paidAt) : Carbon::now();

                    // A. Update Transaksi Payment Gateway
                    if ($transaksi) {
                        $transaksi->update([
                            'status' => StatusTransaksiGateway::Paid,
                            'payload_response' => $callbackData->rawPayload,
                        ]);
                    }

                    // B. Buat Record Pembayaran
                    $channelDetailText = $callbackData->channelDetail ? strtoupper($callbackData->channelDetail) : '';
                    $nominalBayar = $callbackData->amount > 0 ? $callbackData->amount : (float) ($transaksi?->total_tagihan ?? $lockedInvoice->jumlah_setelah_promo);

                    $pembayaran = Pembayaran::create([
                        'invoice_id' => $lockedInvoice->id,
                        'metode' => MetodePembayaran::PaymentGateway,
                        'referensi_transaksi' => $callbackData->paymentReference ?: ($transaksi?->external_id ?? $callbackData->externalId),
                        'jumlah_dibayar' => $nominalBayar,
                        'dibayar_pada' => $dibayarPada,
                        'catatan' => trim("Pembayaran otomatis Xendit {$callbackData->channel} {$channelDetailText}"),
                    ]);

                    // C. Update Status Invoice
                    $lockedInvoice->update([
                        'status' => StatusInvoice::Lunas,
                        'tanggal_lunas' => $dibayarPada->toDateString(),
                        'metode_pembayaran' => MetodePembayaran::PaymentGateway,
                        'xendit_status' => 'PAID',
                    ]);

                    // D. Perpanjang Masa Aktif Layanan Pelanggan
                    $layanan = $lockedInvoice->layananPelanggan()->lockForUpdate()->first();
                    if ($layanan) {
                        $paket = $layanan->paketLayanan;
                        $masaNilai = $paket ? (int) $paket->masa_aktif_nilai : 1;
                        $masaSatuan = $paket ? $paket->masa_aktif_satuan : MasaAktifSatuan::Bulan;

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

                    return;
                }

                // ─── Kasus B: Link Kedaluwarsa (EXPIRED) ────────────────────
                if ($isExpiredStatus) {
                    if ($transaksi) {
                        $transaksi->update([
                            'status' => StatusTransaksiGateway::Expired,
                            'payload_response' => $callbackData->rawPayload,
                        ]);
                    }

                    if (! $lockedInvoice->isLunas()) {
                        $lockedInvoice->update([
                            'xendit_invoice_url' => null,
                            'xendit_status' => 'EXPIRED',
                        ]);
                    }

                    $webhookLog->update(['status_proses' => StatusWebhookLog::Diproses]);

                    return;
                }

                // Status lainnya (misal: PENDING, FAILED)
                $webhookLog->update(['status_proses' => StatusWebhookLog::Diproses]);
            });

            // 6. Pancarkan Event Post-Commit
            if ($eventToDispatch) {
                event($eventToDispatch);
            }

            return response()->json([
                'message' => 'Webhook processed successfully',
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
