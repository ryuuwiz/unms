<?php

namespace App\Livewire\Portal\Invoice\Concerns;

use App\Models\Invoice;
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

        if (! request()->hasValidSignature()) {
            abort(403, 'Tautan tidak valid atau sudah kedaluwarsa. Silakan masuk ke portal pelanggan untuk melihat tagihan ini.');
        }
    }
}
