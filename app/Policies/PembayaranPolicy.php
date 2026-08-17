<?php

namespace App\Policies;

use App\Models\User;

class PembayaranPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pembayaran.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('pembayaran.catat');
    }
}
