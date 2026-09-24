<?php

namespace App\Policies;

use App\Models\Barang;
use App\Models\User;

class BarangPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('barang.lihat');
    }

    public function view(User $user, Barang $barang): bool
    {
        return $user->can('barang.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('barang.buat');
    }

    public function update(User $user, Barang $barang): bool
    {
        return $user->can('barang.ubah');
    }

    public function delete(User $user, Barang $barang): bool
    {
        return $user->can('barang.hapus');
    }
}
