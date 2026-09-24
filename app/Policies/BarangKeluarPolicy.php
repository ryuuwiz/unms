<?php

namespace App\Policies;

use App\Models\BarangKeluar;
use App\Models\User;

class BarangKeluarPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('barang_keluar.lihat');
    }

    public function view(User $user, BarangKeluar $barangKeluar): bool
    {
        return $user->can('barang_keluar.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('barang_keluar.catat');
    }
}
