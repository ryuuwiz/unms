<?php

namespace App\Policies;

use App\Models\Kota;
use App\Models\User;

class WilayahPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('wilayah.lihat');
    }

    public function view(User $user, Kota $kota): bool
    {
        return $user->can('wilayah.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('wilayah.buat');
    }

    public function update(User $user): bool
    {
        return $user->can('wilayah.ubah');
    }

    public function delete(User $user): bool
    {
        return $user->can('wilayah.hapus');
    }
}
