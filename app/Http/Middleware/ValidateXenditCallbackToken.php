<?php

namespace App\Http\Middleware;

use App\Services\Xendit\XenditWebhookVerifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

            return response()->json([
                'message' => 'Unauthorized: Invalid callback token',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
