<?php

namespace App\Policies;

use App\Models\LayananPelanggan;
use App\Models\User;

class LayananPelangganPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('layanan_pelanggan.lihat');
    }

    public function view(User $user, LayananPelanggan $layananPelanggan): bool
    {
        return $user->can('layanan_pelanggan.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('layanan_pelanggan.buat');
    }

    public function update(User $user, LayananPelanggan $layananPelanggan): bool
    {
        return $user->can('layanan_pelanggan.ubah');
    }

    public function delete(User $user, LayananPelanggan $layananPelanggan): bool
    {
        return $user->can('layanan_pelanggan.hapus');
    }
}
