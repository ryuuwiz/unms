<?php

namespace App\Policies;

use App\Models\PaketLayanan;
use App\Models\User;

class PaketLayananPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('paket_layanan.lihat');
    }

    public function view(User $user, PaketLayanan $paketLayanan): bool
    {
        return $user->can('paket_layanan.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('paket_layanan.buat');
    }

    public function update(User $user, PaketLayanan $paketLayanan): bool
    {
        return $user->can('paket_layanan.ubah');
    }

    public function delete(User $user, PaketLayanan $paketLayanan): bool
    {
        return $user->can('paket_layanan.hapus');
    }
}
