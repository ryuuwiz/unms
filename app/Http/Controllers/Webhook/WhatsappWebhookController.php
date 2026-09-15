<?php

namespace App\Http\Controllers\Webhook;

use App\Enums\StatusWebhookLog;
use App\Http\Controllers\Controller;
use App\Jobs\Whatsapp\ProcessWhatsappWebhookJob;
use App\Services\Whatsapp\WhatsappWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Sentry\Severity;
use Sentry\State\Scope;

class WhatsappWebhookController extends Controller
{
    /**
     * WA gateway payloads are small JSON; anything past this is not a legitimate delivery.
     * Rejected before body parsing/encryption to bound the cost of an oversized request.
     */
    protected const MAX_BODY_BYTES = 1_048_576; // 1MB

    public function __construct(
        protected WhatsappWebhookService $webhookService
    ) {}

    /**
     * Unified entrypoint for all WhatsApp gateway webhook callbacks (GOWA/WAHA).
     *
     * Processing is queued (ProcessWhatsappWebhookJob), not synchronous: this route has no
     * per-connection auth guaranteed (see resolveGowaSysblas()/verifyGowaSignature()), so
     * running ticket/DB writes and outbound-send triggers inline on every request made this
     * endpoint a trivial DoS vector against the shared PHP-FPM pool. Rate limiting (routes/web.php)
     * and the body-size guard above are the other two legs of that fix.
     */
    public function handle(Request $request): JsonResponse
    {
        if ((int) $request->header('Content-Length', 0) > self::MAX_BODY_BYTES) {
            return response()->json([
                'status' => false,
                'type' => 'payload_too_large',
                'message' => 'Payload webhook melebihi batas ukuran yang diizinkan.',
            ], 413);
        }

        $payload = $request->all();

        // Jika payload kosong tapi ada raw json body
        if (empty($payload)) {
            $payload = (array) json_decode($request->getContent(), true);
        }

        // Dicatat SEBELUM verifikasi signature (bukan di dalam WhatsappWebhookService::process()
        // seperti sebelumnya) agar percobaan yang ditolak juga meninggalkan jejak audit, bukan
        // hanya percobaan yang berhasil -- lihat WebhookLog::status_proses = Gagal di bawah.
        $log = $this->webhookService->logWebhook($payload);

        if (! $log) {
            return response()->json([
                'status' => false,
                'type' => 'error',
                'message' => 'Gagal mencatat webhook, silakan coba lagi.',
            ], 503);
        }

        $gowaSysblas = $this->webhookService->resolveGowaSysblas($payload);
        if ($gowaSysblas && ! $this->webhookService->verifyGowaSignature($request, $gowaSysblas)) {
            $log->update([
                'status_proses' => StatusWebhookLog::Gagal,
                'catatan_error' => 'Signature webhook tidak valid.',
            ]);

            // Tidak ada exception object untuk sekadar signature yang tidak cocok -- captureMessage,
            // bukan captureException. Jangan pernah sertakan nilai signature/secret di context.
            \Sentry\configureScope(function (Scope $scope) use ($gowaSysblas): void {
                $scope->setContext('whatsapp_webhook_rejected', [
                    'sysblas_id' => $gowaSysblas->id,
                    'session_name' => $gowaSysblas->session_name,
                ]);
            });
            \Sentry\captureMessage('WhatsApp webhook signature tidak valid.', Severity::warning());

            return response()->json([
                'status' => false,
                'type' => 'unauthorized',
                'message' => 'Signature webhook tidak valid.',
            ], 401);
        }

        ProcessWhatsappWebhookJob::dispatch($log->id);

        return response()->json([
            'status' => true,
            'type' => 'queued',
            'message' => 'Webhook diterima dan dimasukkan ke antrean pemrosesan.',
        ], 200);
    }
}
