<?php

namespace App\Services\Xendit;

use Illuminate\Http\Request;

class XenditWebhookVerifier
{
    /**
     * Verifikasi header x-callback-token menggunakan perbandingan konstan hash_equals.
     */
    public function verifikasi(Request $request): bool
    {
        $expectedToken = (string) config('services.xendit.callback_token');

        if (empty($expectedToken)) {
            return false;
        }

        $receivedToken = (string) ($request->header('x-callback-token') ?? $request->header('X-Callback-Token', ''));

        if (empty($receivedToken)) {
            return false;
        }

        return hash_equals($expectedToken, $receivedToken);
    }
}
