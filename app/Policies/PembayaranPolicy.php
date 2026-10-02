<?php

namespace App\Policies;

use App\Models\User;

class PembayaranPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pembayaran.lihat');
    }

    public function view(User $user): bool
    {
        return $user->can('pembayaran.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('pembayaran.catat');
    }

    /**
     * Lunasi Pelunasan Susulan dari halaman dan tandai kasusnya Sudah Ditangani.
     */
    public function kelolaPelunasanSusulan(User $user): bool
    {
        return $user->can('payment_gateway.ubah');
    }
}
