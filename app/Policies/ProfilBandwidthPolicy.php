<?php

namespace App\Policies;

use App\Models\ProfilBandwidth;
use App\Models\User;

class ProfilBandwidthPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('profil_bandwidth.lihat');
    }

    public function view(User $user, ProfilBandwidth $profilBandwidth): bool
    {
        return $user->can('profil_bandwidth.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('profil_bandwidth.buat');
    }

    public function update(User $user, ProfilBandwidth $profilBandwidth): bool
    {
        return $user->can('profil_bandwidth.ubah');
    }

    public function delete(User $user, ProfilBandwidth $profilBandwidth): bool
    {
        return $user->can('profil_bandwidth.hapus');
    }
}
