<?php

namespace App\Livewire\Portal\Invoice\Concerns;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

trait AuthorizesInvoiceAccess
{
    /**
     * Izinkan akses jika salah satu benar (ADR-0067):
     * (a) request datang lewat Tautan Tagihan `/t/{token}` -- token itu sendiri kunci aksesnya,
     * (b) request memakai signed URL lama yang tanda tangannya asli untuk invoice ini, berapa
     *     pun tanggal kedaluwarsanya (tautan yang sudah terkirim tetap bisa dibuka), atau
     * (c) ada sesi pelanggan login dan invoice ini miliknya.
     */
    protected function authorizeAksesTagihan(Invoice $invoice): void
    {
        if ($this->lewatTautanTagihan($invoice) || $this->hasValidInvoiceSignature()) {
            return;
        }

        $pelanggan = Auth::guard('pelanggan')->user();

        if (! $pelanggan) {
            abort(403, 'Tautan tidak valid. Silakan masuk ke portal pelanggan untuk melihat tagihan ini.');
        }

        if ($invoice->pelanggan_id !== $pelanggan->pelanggan_id) {
            abort(403, 'Anda tidak memiliki akses ke tagihan ini.');
        }
    }

    protected function lewatTautanTagihan(Invoice $invoice): bool
    {
        $request = request();
        $invoiceDariRute = $request->route('invoice');

        return $request->routeIs('portal.tagihan.tautan', 'portal-legacy.tagihan.tautan')
            && $invoiceDariRute instanceof Invoice
            && ! empty($invoice->token_tautan)
            && hash_equals($invoice->token_tautan, (string) $invoiceDariRute->token_tautan);
    }

    /**
     * Verifikasi keaslian tanda tangan URL tanpa memeriksa masa berlaku, dengan toleransi
     * reverse proxy (Traefik/Caddy/Dokploy SSL stripping).
     */
    protected function hasValidInvoiceSignature(): bool
    {
        $request = request();

        if (! $request->has('signature')) {
            return false;
        }

        if ($this->tandaTanganAsli($request)) {
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

            return $this->tandaTanganAsli($secureRequest);
        }

        return false;
    }

    protected function tandaTanganAsli(Request $request): bool
    {
        return URL::hasCorrectSignature($request) || URL::hasCorrectSignature($request, absolute: false);
    }
}
