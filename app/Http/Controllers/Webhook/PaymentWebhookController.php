<?php

namespace App\Http\Controllers\Webhook;

use App\DTO\PaymentGateway\PaymentCallbackData;
use App\Enums\StatusWebhookLog;
use App\Http\Controllers\Controller;
use App\Jobs\PaymentGateway\ProcessPaymentWebhookJob;
use App\Models\TransaksiPaymentGateway;
use App\Models\WebhookLog;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Sentry\Severity;
use Sentry\State\Scope;

class PaymentWebhookController extends Controller
{
    public function __construct(
        protected PaymentGatewayManager $manager
    ) {}

    /**
     * Handle incoming payment gateway webhook callback for any supported gateway asynchronously.
     */
    public function handle(Request $request, string $gateway): JsonResponse
    {
        $gateway = strtolower(trim($gateway));
        $payload = $request->all();

        try {
            $driver = $this->manager->driver($gateway);
        } catch (\Throwable $e) {
            Log::warning("Webhook gateway [{$gateway}] tidak didukung.");

            return response()->json(['message' => "Unsupported gateway: {$gateway}"], 400);
        }

        // 1. Normalisasi payload dan cari transaksinya lebih dulu -- hanya membaca, belum
        // menulis apa pun -- karena token callback diverifikasi dengan kredensial Koneksi
        // Payment Gateway yang menerbitkan transaksi itu, bukan koneksi pertama/default
        // (ADR-0067). Transaksi tak dikenal (mis. tombol "Test" dashboard gateway) memakai
        // koneksi aktif & default.
        /** @var PaymentCallbackData $callbackData */
        $callbackData = $driver->parseWebhookPayload($request);
        $transaksi = $this->cariTransaksi($callbackData);
        $setting = $this->manager->settingUntukTransaksi($transaksi, $gateway);

        // 2. Verifikasi Signature / Token Callback
        // Tidak boleh ada bypass environment di sini: verifikasi wajib aktif juga saat pengujian
        // agar tes penolakan signature benar-benar membuktikan perilaku produksi.
        if (! $driver->verifyWebhook($request, $setting)) {
            // Jangan pernah mencatat header mentah: header memuat callback token rahasia.
            Log::warning("Webhook signature/token tidak valid untuk gateway [{$gateway}].", [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'pengaturan_gateway_id' => $setting->id,
            ]);

            // Payload sengaja tidak disimpan: tanpa token sah isinya tidak dapat dipercaya.
            // Pemulihan pembayaran yang tertolak dilakukan dengan bertanya langsung ke API
            // gateway (pembayaran:rekonsiliasi / pembayaran:pulihkan).
            WebhookLog::create([
                'provider' => $gateway,
                'event_type' => 'webhook.token_rejected',
                'payload' => null,
                'status_proses' => StatusWebhookLog::Gagal,
                'catatan_error' => "Signature/token webhook tidak valid untuk koneksi [{$setting->nama}].",
                'diterima_pada' => Carbon::now(),
            ]);

            \Sentry\configureScope(function (Scope $scope) use ($gateway, $request, $setting): void {
                $scope->setContext('payment_webhook_rejected', [
                    'gateway' => $gateway,
                    'ip' => $request->ip(),
                    'pengaturan_gateway_id' => $setting->id,
                    'sandbox_mode' => $setting->sandbox_mode,
                ]);
            });
            \Sentry\captureMessage("Webhook {$gateway}: signature/token tidak valid.", Severity::warning());

            return response()->json(['message' => 'Unauthorized / Invalid webhook signature'], 401);
        }

        // 3. Catat Webhook Log secara idempoten.
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

        // 4. Tangani uji coba simulasi dummy dari dashboard gateway secara langsung
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

        // 5. Dispatch Asynchronous Queue Job untuk pemrosesan di latar belakang
        ProcessPaymentWebhookJob::dispatch($webhookLog->id);

        Log::info("Webhook {$gateway} diterima dan dimasukkan ke antrean pemrosesan.", [
            'webhook_log_id' => $webhookLog->id,
            'event_id' => $callbackData->eventId,
            'external_id' => $callbackData->externalId,
        ]);

        // 6. Respon HTTP 200 instan memenuhi SLA Webhook Gateway (< 100ms)
        return response()->json([
            'message' => 'Webhook received and queued for processing',
            'event_id' => $callbackData->eventId,
            'status' => 'QUEUED',
        ], 200);
    }

    /**
     * Browser yang mendarat di URL webhook (simulator sandbox iPaymu membuka notifyUrl dengan GET).
     * Isi request tidak dipercaya: transaksi yang disebut hanya dicek statusnya ke API gateway,
     * lalu browser diarahkan ke portal. Sengaja bukan ke Tautan Tagihan, karena reference_id bisa
     * ditebak dan Tautan Tagihan memberi akses ke tagihan.
     */
    public function kembali(Request $request, string $gateway): RedirectResponse
    {
        $referensi = (string) ($request->query('reference_id') ?? $request->query('referenceId') ?? '');
        $transaksi = $referensi !== ''
            ? TransaksiPaymentGateway::where('external_id', $referensi)->where('gateway', strtolower($gateway))->first()
            : null;

        if ($transaksi) {
            try {
                $this->manager->sinkronkanTransaksi($transaksi);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('portal.invoice.index');
    }

    private function cariTransaksi(PaymentCallbackData $callbackData): ?TransaksiPaymentGateway
    {
        if (! empty($callbackData->externalId)) {
            $transaksi = TransaksiPaymentGateway::where('external_id', $callbackData->externalId)->first();
            if ($transaksi) {
                return $transaksi;
            }
        }

        if (empty($callbackData->eventId)) {
            return null;
        }

        return TransaksiPaymentGateway::where('provider_reference_id', $callbackData->eventId)->first();
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
