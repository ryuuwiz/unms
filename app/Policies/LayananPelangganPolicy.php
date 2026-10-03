<?php

namespace App\Policies;

use App\Models\LayananPelanggan;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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

    /**
     * Layanan milik pelanggan terhapus tidak bisa diubah, diisolir, atau diprovisi sampai pelanggannya dipulihkan.
     */
    public function update(User $user, LayananPelanggan $layananPelanggan): Response
    {
        if (! $user->can('layanan_pelanggan.ubah')) {
            return Response::deny();
        }

        return $layananPelanggan->milikPelangganTerhapus()
            ? Response::deny('Pelanggan pemilik layanan ini sudah dihapus. Pulihkan pelanggan terlebih dahulu untuk mengubah layanannya.')
            : Response::allow();
    }

    public function delete(User $user, LayananPelanggan $layananPelanggan): bool
    {
        return $user->can('layanan_pelanggan.hapus');
    }

    /**
     * Apakah user bisa mengungkap PPP Password (plaintext) layanan ini.
     */
    public function viewPppPassword(User $user, LayananPelanggan $layananPelanggan): bool
    {
        return $user->can('layanan_pelanggan.lihat_ppp_password');
    }
}
