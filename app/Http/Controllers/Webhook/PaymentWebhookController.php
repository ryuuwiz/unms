<?php

namespace App\Http\Controllers\Webhook;

use App\DTO\PaymentGateway\PaymentCallbackData;
use App\Enums\StatusTransaksiGateway;
use App\Enums\StatusWebhookLog;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use App\Models\WebhookLog;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(
        protected PaymentGatewayManager $manager
    ) {}

    /**
     * Handle incoming payment gateway webhook callback for any supported gateway.
     */
    public function handle(Request $request, string $gateway = 'xendit'): JsonResponse
    {
        $gateway = strtolower(trim($gateway));
        $payload = $request->all();

        try {
            $driver = $this->manager->driver($gateway);
        } catch (\Throwable $e) {
            Log::warning("Webhook gateway [{$gateway}] tidak didukung.");

            return response()->json(['message' => "Unsupported gateway: {$gateway}"], 400);
        }

        $setting = PengaturanGateway::getSettingForProvider($gateway);
        if (! $setting) {
            $setting = PengaturanGateway::getDefault();
        }

        if (! $setting) {
            $setting = PengaturanGateway::getXenditSetting();
        }

        // 1. Verifikasi Signature / Token Callback
        if (! $driver->verifyWebhook($request, $setting) && ! app()->environment('testing')) {
            Log::warning("Webhook signature/token tidak valid untuk gateway [{$gateway}].", [
                'headers' => $request->headers->all(),
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Unauthorized / Invalid webhook signature'], 401);
        }

        // 2. Normalisasi Payload Callback via Driver DTO
        /** @var PaymentCallbackData $callbackData */
        $callbackData = $driver->parseWebhookPayload($request);

        // 3. Idempotency Check Awal pada Webhook Log
        if (! empty($callbackData->eventId)) {
            $existingLog = WebhookLog::where('xendit_event_id', $callbackData->eventId)
                ->where('status_proses', StatusWebhookLog::Diproses)
                ->first();

            if ($existingLog) {
                return response()->json([
                    'message' => 'Webhook already processed',
                    'event_id' => $callbackData->eventId,
                ], 200);
            }
        }

        // 4. Catat Webhook Log
        $webhookLog = WebhookLog::create([
            'event_type' => "payment.{$gateway}",
            'xendit_event_id' => $callbackData->eventId,
            'payload' => $payload,
            'status_proses' => StatusWebhookLog::Diterima,
            'diterima_pada' => Carbon::now(),
        ]);

        // 5. Cari Transaksi Payment Gateway / Invoice terkait
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
            // Tangani simulasi test webhook dari dashboard
            if ($callbackData->isTest) {
                $webhookLog->update([
                    'status_proses' => StatusWebhookLog::Diproses,
                    'catatan_error' => "Simulasi uji coba webhook dari Dashboard {$gateway} berhasil diverifikasi.",
                ]);

                Log::info("Test Webhook ({$gateway}) berhasil diverifikasi.", [
                    'external_id' => $callbackData->externalId,
                    'event_id' => $callbackData->eventId,
                ]);

                return response()->json([
                    'status' => 'SUCCESS',
                    'message' => "Test webhook callback for {$gateway} verified and acknowledged successfully",
                    'external_id' => $callbackData->externalId,
                    'is_test' => true,
                ], 200);
            }

            $webhookLog->update([
                'status_proses' => StatusWebhookLog::Diabaikan,
                'catatan_error' => "Tagihan/Transaksi dengan identifier [{$callbackData->externalId} / {$callbackData->eventId}] tidak ditemukan.",
            ]);

            Log::warning("Webhook {$gateway} diabaikan: Target [{$callbackData->externalId}] tidak ditemukan.");

            return response()->json([
                'message' => 'Target invoice or transaction not found, ignored',
                'external_id' => $callbackData->externalId,
            ], 200);
        }

        if ($transaksi) {
            $webhookLog->update(['transaksi_payment_gateway_id' => $transaksi->id]);
        }

        // 6. Eksekusi Transaksi Database Terisolasi dengan Row Locking
        try {
            if ($callbackData->isPaid()) {
                $this->manager->prosesPelunasan(
                    invoice: $invoice,
                    payloadOrData: $callbackData,
                    transaksi: $transaksi,
                    webhookLog: $webhookLog
                );

                Log::info("Pembayaran invoice {$invoice->no_invoice} berhasil dicatat via {$gateway}", [
                    'invoice_id' => $invoice->id,
                    'external_id' => $callbackData->externalId,
                ]);

                return response()->json([
                    'message' => 'Payment processed successfully',
                    'external_id' => $callbackData->externalId,
                ], 200);
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

            Log::error("Gagal memproses webhook {$gateway} untuk Invoice [{$invoice->no_invoice}]: ".$e->getMessage(), [
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
