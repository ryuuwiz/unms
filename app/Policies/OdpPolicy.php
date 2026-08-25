<?php

namespace App\Policies;

use App\Models\Odp;
use App\Models\User;

class OdpPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('odp.lihat');
    }

    public function view(User $user, Odp $odp): bool
    {
        return $user->can('odp.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('odp.buat');
    }

    public function update(User $user, Odp $odp): bool
    {
        return $user->can('odp.ubah');
    }

    public function delete(User $user, Odp $odp): bool
    {
        return $user->can('odp.hapus');
    }
}
