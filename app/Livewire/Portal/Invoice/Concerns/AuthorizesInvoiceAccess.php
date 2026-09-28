<?php

namespace App\Livewire\Portal\Invoice\Concerns;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait AuthorizesInvoiceAccess
{
    /**
     * Izinkan akses jika: (a) ada sesi pelanggan login dan invoice ini miliknya, atau
     * (b) request memakai tautan bertanda tangan (signed URL) yang valid untuk invoice
     * ini -- dikirim via notifikasi WhatsApp/email agar dapat dibuka tanpa login.
     */
    protected function authorizeAksesTagihan(Invoice $invoice): void
    {
        $pelanggan = Auth::guard('pelanggan')->user();

        if ($pelanggan) {
            if ($invoice->pelanggan_id !== $pelanggan->pelanggan_id) {
                abort(403, 'Anda tidak memiliki akses ke tagihan ini.');
            }

            return;
        }

        if (! $this->hasValidInvoiceSignature()) {
            abort(403, 'Tautan tidak valid atau sudah kedaluwarsa. Silakan masuk ke portal pelanggan untuk melihat tagihan ini.');
        }
    }

    /**
     * Verifikasi tanda tangan URL dengan toleransi reverse proxy (Traefik/Caddy/Dokploy SSL stripping).
     */
    protected function hasValidInvoiceSignature(): bool
    {
        $request = request();

        if ($request->hasValidSignature() || $request->hasValidRelativeSignature()) {
            return true;
        }

        // Jika request tiba via HTTP di jaringan internal Docker (reverse proxy SSL terminasi di Traefik),
        // lakukan evaluasi ulang tanda tangan menggunakan skema HTTPS.
        if (! $request->secure()) {
            $secureRequest = Request::create(
                uri: preg_replace('/^http:/i', 'https:', $request->fullUrl()),
                method: $request->method(),
                parameters: $request->query->all(),
                cookies: $request->cookies->all(),
                files: $request->files->all(),
                server: array_merge($request->server->all(), [
                    'HTTPS' => 'on',
                    'SERVER_PORT' => 443,
                    'HTTP_X_FORWARDED_PROTO' => 'https',
                ]),
            );

            if ($secureRequest->hasValidSignature() || $secureRequest->hasValidRelativeSignature()) {
                return true;
            }
        }

        return false;
    }
}
