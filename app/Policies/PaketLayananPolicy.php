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

    /**
     * Kelola Router Paket (ADR-0063): konfigurasi teknis, jadi pemegang izin ubah paket dan NOC (izin ubah router).
     */
    public function kelolaRouter(User $user, PaketLayanan $paketLayanan): bool
    {
        return $user->can('paket_layanan.ubah') || $user->can('router.ubah');
    }

    public function delete(User $user, PaketLayanan $paketLayanan): bool
    {
        return $user->can('paket_layanan.hapus');
    }
}
