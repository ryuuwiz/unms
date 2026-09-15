<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('invoice.lihat');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('invoice.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('invoice.buat');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->can('invoice.hapus');
    }

    public function cetak(User $user, Invoice $invoice): bool
    {
        return $user->can('invoice.cetak');
    }

    /**
     * Kirim uji coba notifikasi tagihan (email & WhatsApp) -- dibatasi super_admin
     * karena mengirim pesan nyata ke tujuan bebas, bukan sekadar melihat data invoice.
     */
    public function kirimUjiCoba(User $user, Invoice $invoice): bool
    {
        return $user->hasRole('super_admin');
    }
}
