<?php

namespace App\Jobs\Whatsapp;

use App\Enums\StatusWebhookLog;
use App\Models\WebhookLog;
use App\Services\Whatsapp\WhatsappWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Sentry\State\Scope;
use Throwable;

/**
 * Proses payload webhook WhatsApp (GOWA/WAHA) di antrean latar belakang, bukan di thread
 * request HTTP -- lihat WhatsappWebhookController::handle(). Sebelumnya seluruh pemrosesan
 * (lookup pelanggan, tulis histori tiket, trigger auto-reply) berjalan sinkron tanpa rate
 * limit maupun batas ukuran body, menjadikannya vektor DoS trivial terhadap pool PHP-FPM
 * bersama. Mirip ProcessPaymentWebhookJob, tapi antrean `wa-blast` (bukan `payments`) --
 * lihat supervisor-low di config/horizon.php.
 */
class ProcessWhatsappWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public int $webhookLogId
    ) {
        $this->onQueue('wa-blast');
    }

    public function handle(WhatsappWebhookService $service): void
    {
        /** @var WebhookLog|null $webhookLog */
        $webhookLog = WebhookLog::find($this->webhookLogId);

        if (! $webhookLog) {
            Log::warning("ProcessWhatsappWebhookJob diabaikan: WebhookLog ID {$this->webhookLogId} tidak ditemukan.");

            return;
        }

        // Idempotensi: jika log sudah diproses (mis. redelivery gateway), hindari eksekusi ulang.
        if ($webhookLog->status_proses === StatusWebhookLog::Diproses) {
            Log::info("ProcessWhatsappWebhookJob diabaikan: WebhookLog ID {$this->webhookLogId} sudah berstatus diproses.");

            return;
        }

        $payload = (array) ($webhookLog->payload ?? []);

        $service->handlePayload($payload, $webhookLog);
    }

    /**
     * Lihat ProcessPaymentWebhookJob::failed() untuk rasionalisasi capture Sentry eksplisit --
     * Horizon dashboard dikunci di lingkungan ini dan sentry.php enable_logs=false, jadi ini
     * satu-satunya jalur kegagalan job antrean WhatsApp yang benar-benar terlihat.
     */
    public function failed(?Throwable $exception): void
    {
        Log::critical("ProcessWhatsappWebhookJob GAGAL TOTAL untuk WebhookLog ID {$this->webhookLogId}: ".$exception?->getMessage(), [
            'exception' => $exception,
        ]);

        if ($exception) {
            \Sentry\configureScope(function (Scope $scope): void {
                $scope->setContext('whatsapp_webhook', [
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
