<?php

namespace App\Services\Xendit;

use App\Models\PengaturanGateway;
use Illuminate\Http\Request;

class XenditWebhookVerifier
{
    /**
     * Verifikasi header x-callback-token menggunakan perbandingan konstan hash_equals.
     *
     * Sumber token: kredensial DB PengaturanGateway lebih diutamakan, fallback ke config/env --
     * persis urutan yang dipakai XenditDriver::getCallbackToken(). Sebelumnya method ini HANYA
     * membaca config/env, sumber terpisah dari yang dipakai XenditDriver untuk rute kanonik
     * `/webhook/payment/xendit` -- rotasi token lewat UI PengaturanGateway tanpa memperbarui
     * .env akan diam-diam membuat rute legacy `/webhook/xendit*` menolak seluruh callback asli.
     */
    public function verifikasi(Request $request): bool
    {
        $expectedToken = (string) (PengaturanGateway::getXenditSetting()->getCredential('callback_token')
            ?: config('services.xendit.callback_token'));

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
