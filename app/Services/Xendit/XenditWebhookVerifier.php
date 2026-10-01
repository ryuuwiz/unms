<?php

namespace App\Services\Xendit;

use App\Models\PengaturanGateway;
use Illuminate\Http\Request;

class XenditWebhookVerifier
{
    /**
     * Verifikasi header x-callback-token menggunakan token terenkripsi di database.
     */
    public function verifikasi(Request $request): bool
    {
        $expectedToken = (string) PengaturanGateway::getXenditSetting()->getCredential('callback_token', '');

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
