<?php

namespace App\Http\Middleware;

use App\Enums\StatusWebhookLog;
use App\Models\WebhookLog;
use App\Services\Xendit\XenditWebhookVerifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Sentry\Severity;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

class ValidateXenditCallbackToken
{
    public function __construct(
        protected XenditWebhookVerifier $verifier
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->verifier->verifikasi($request)) {
            Log::warning('Webhook Xendit ditolak oleh middleware: Callback Token tidak valid atau kosong.', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Jejak audit untuk percobaan yang ditolak di jalur legacy juga, konsisten dengan
            // PaymentWebhookController::handle().
            WebhookLog::create([
                'provider' => 'xendit',
                'event_type' => 'webhook.token_rejected',
                'payload' => null,
                'status_proses' => StatusWebhookLog::Gagal,
                'catatan_error' => 'Callback token tidak valid atau kosong.',
                'diterima_pada' => Carbon::now(),
            ]);

            \Sentry\configureScope(function (Scope $scope) use ($request): void {
                $scope->setContext('payment_webhook_rejected', [
                    'gateway' => 'xendit',
                    'ip' => $request->ip(),
                ]);
            });
            \Sentry\captureMessage('Webhook xendit (legacy route): callback token tidak valid.', Severity::warning());

            return response()->json([
                'message' => 'Unauthorized: Invalid callback token',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
