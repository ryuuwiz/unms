<?php

namespace App\Http\Controllers\Webhook;

use App\DTO\Xendit\XenditCallbackData;
use App\Enums\StatusTransaksiGateway;
use App\Enums\StatusWebhookLog;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\TransaksiPaymentGateway;
use App\Models\WebhookLog;
use App\Services\Xendit\XenditPaymentService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class XenditWebhookController extends Controller
{
    /**
     * Handle incoming Xendit webhook callback.
     * Webhook request telah divalidasi oleh middleware 'ValidateXenditCallbackToken'.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        // 1. Parsing & Normalisasi Payload via DTO
        $callbackData = XenditCallbackData::fromWebhookPayload($payload);

        // 2. Idempotency Check Awal pada Webhook Log
        if (! empty($callbackData->eventId)) {
            $existingLog = WebhookLog::where('xendit_event_id', $callbackData->eventId)
                ->where('status_proses', StatusWebhookLog::Diproses)
                ->first();

            if ($existingLog) {
                return response()->json([
                    'message' => 'Webhook already processed',
                    'xendit_event_id' => $callbackData->eventId,
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
            if (is_numeric($callbackData->externalId)) {
                $invoice = Invoice::find((int) $callbackData->externalId);
            } else {
                $invoice = Invoice::where('no_invoice', $callbackData->externalId)->first();
                if (! $invoice && preg_match('/(INV-\d{6}-\d+)/', $callbackData->externalId, $matches)) {
                    $invoice = Invoice::where('no_invoice', $matches[1])->first();
                }
            }
        }

        if (! $invoice) {
            // Tangani simulasi test webhook dari Dashboard Xendit (misal: external_id = invoice_123124123)
            if ($callbackData->isTestDummy()) {
                $webhookLog->update([
                    'status_proses' => StatusWebhookLog::Diproses,
                    'catatan_error' => 'Simulasi uji coba webhook dari Dashboard Xendit berhasil diverifikasi.',
                ]);

                Log::info("Test Webhook Xendit ({$callbackData->eventType}) berhasil diverifikasi dari Dashboard Xendit.", [
                    'external_id' => $callbackData->externalId,
                    'event_id' => $callbackData->eventId,
                ]);

                return response()->json([
                    'status' => 'SUCCESS',
                    'message' => 'Xendit test webhook callback verified and acknowledged successfully',
                    'external_id' => $callbackData->externalId,
                    'is_test' => true,
                ], 200);
            }

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
            if ($isPaidStatus) {
                $paymentService = app(XenditPaymentService::class);
                $paymentService->prosesPelunasanDariXendit(
                    invoice: $invoice,
                    payloadOrData: $callbackData,
                    transaksi: $transaksi,
                    webhookLog: $webhookLog
                );

                Log::info("Pembayaran invoice {$invoice->no_invoice} berhasil dicatat via payment_gateway", [
                    'invoice_id' => $invoice->id,
                    'external_id' => $callbackData->externalId,
                ]);

                return response()->json([
                    'message' => 'Payment processed successfully',
                    'external_id' => $callbackData->externalId,
                ], 200);
            }

            if ($isExpiredStatus) {
                DB::transaction(function () use ($invoice, $transaksi, $callbackData, $webhookLog) {
                    /** @var Invoice $lockedInvoice */
                    $lockedInvoice = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

                    if (! $lockedInvoice->isLunas()) {
                        $lockedInvoice->update([
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

                return response()->json([
                    'message' => 'Invoice expiration processed',
                    'external_id' => $callbackData->externalId,
                ], 200);
            }

            $webhookLog->update(['status_proses' => StatusWebhookLog::Diproses]);

            return response()->json([
                'message' => 'Webhook received and recorded',
                'status' => $callbackData->status,
            ], 200);
        } catch (Exception $e) {
            $webhookLog->update([
                'status_proses' => StatusWebhookLog::Gagal,
                'catatan_error' => $e->getMessage(),
            ]);

            Log::error("Gagal memproses webhook Xendit untuk Invoice [{$invoice->no_invoice}]: ".$e->getMessage(), [
                'exception' => $e,
                'payload' => $payload,
            ]);

            return response()->json([
                'message' => 'Error processing webhook transaction',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
