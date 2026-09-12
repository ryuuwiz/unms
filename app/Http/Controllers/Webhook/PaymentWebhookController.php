<?php

namespace App\Http\Controllers\Webhook;

use App\DTO\PaymentGateway\PaymentCallbackData;
use App\Enums\StatusWebhookLog;
use App\Http\Controllers\Controller;
use App\Jobs\PaymentGateway\ProcessPaymentWebhookJob;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use App\Models\WebhookLog;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(
        protected PaymentGatewayManager $manager
    ) {}

    /**
     * Handle incoming payment gateway webhook callback for any supported gateway asynchronously.
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
        // Tidak boleh ada bypass environment di sini: verifikasi wajib aktif juga saat pengujian
        // agar tes penolakan signature benar-benar membuktikan perilaku produksi.
        if (! $driver->verifyWebhook($request, $setting)) {
            // Jangan pernah mencatat header mentah: header memuat callback token rahasia.
            Log::warning("Webhook signature/token tidak valid untuk gateway [{$gateway}].", [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['message' => 'Unauthorized / Invalid webhook signature'], 401);
        }

        // 2. Normalisasi Payload Callback via Driver DTO
        /** @var PaymentCallbackData $callbackData */
        $callbackData = $driver->parseWebhookPayload($request);

        // 3. Cari Transaksi Payment Gateway terkait untuk relasi audit trail instan
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

        // 4. Catat Webhook Log secara idempoten.
        // Unique index `webhook_log_provider_event_unique` adalah penjaga sebenarnya:
        // firstOrCreate menangani redelivery berurutan, sedangkan tangkapan QueryException
        // menangani redelivery paralel yang kalah balapan pada index. Keduanya wajib membalas
        // HTTP 200 agar gateway berhenti melakukan retry.
        $eventType = ! empty($payload['event']) ? (string) $payload['event'] : "payment.{$gateway}";
        $eventId = ! empty($callbackData->eventId) ? $callbackData->eventId : null;

        $atributLog = [
            'provider' => $gateway,
            'provider_event_id' => $eventId,
            'transaksi_payment_gateway_id' => $transaksi?->id,
            'event_type' => $eventType,
            'xendit_event_id' => $eventId,
            'payload' => $payload,
            'status_proses' => StatusWebhookLog::Diterima,
            'diterima_pada' => Carbon::now(),
        ];

        try {
            if ($eventId) {
                $webhookLog = WebhookLog::firstOrCreate(
                    ['provider' => $gateway, 'provider_event_id' => $eventId],
                    $atributLog
                );

                if (! $webhookLog->wasRecentlyCreated) {
                    return $this->responsSudahDiproses($eventId);
                }
            } else {
                $webhookLog = WebhookLog::create($atributLog);
            }
        } catch (QueryException $e) {
            Log::info("Webhook {$gateway} duplikat ditolak oleh unique index database.", [
                'event_id' => $eventId,
            ]);

            return $this->responsSudahDiproses($eventId);
        }

        // 5. Tangani uji coba simulasi dummy dari dashboard gateway secara langsung
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

        // 6. Dispatch Asynchronous Queue Job untuk pemrosesan di latar belakang
        ProcessPaymentWebhookJob::dispatch($webhookLog->id);

        Log::info("Webhook {$gateway} diterima dan dimasukkan ke antrean pemrosesan.", [
            'webhook_log_id' => $webhookLog->id,
            'event_id' => $callbackData->eventId,
            'external_id' => $callbackData->externalId,
        ]);

        // 7. Respon HTTP 200 instan memenuhi SLA Webhook Gateway (< 100ms)
        return response()->json([
            'message' => 'Webhook received and queued for processing',
            'event_id' => $callbackData->eventId,
            'status' => 'QUEUED',
        ], 200);
    }

    /**
     * Balasan idempoten untuk callback yang event id-nya sudah pernah tercatat.
     */
    private function responsSudahDiproses(?string $eventId): JsonResponse
    {
        return response()->json([
            'message' => 'Webhook already processed',
            'event_id' => $eventId,
            'status' => 'ALREADY_PROCESSED',
        ], 200);
    }
}
