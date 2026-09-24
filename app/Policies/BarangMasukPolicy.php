<?php

namespace App\Policies;

use App\Models\BarangMasuk;
use App\Models\User;

class BarangMasukPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('barang_masuk.lihat');
    }

    public function view(User $user, BarangMasuk $barangMasuk): bool
    {
        return $user->can('barang_masuk.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('barang_masuk.catat');
    }
}
