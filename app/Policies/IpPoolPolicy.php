<?php

namespace App\Policies;

use App\Models\IpPool;
use App\Models\User;

class IpPoolPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ip_pool.lihat');
    }

    public function view(User $user, IpPool $ipPool): bool
    {
        return $user->can('ip_pool.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('ip_pool.buat');
    }

    public function update(User $user, IpPool $ipPool): bool
    {
        return $user->can('ip_pool.ubah');
    }

    public function delete(User $user, IpPool $ipPool): bool
    {
        return $user->can('ip_pool.hapus');
    }
}
