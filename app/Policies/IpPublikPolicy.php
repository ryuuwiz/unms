<?php

namespace App\Policies;

use App\Models\IpPublik;
use App\Models\User;

class IpPublikPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ip_publik.lihat');
    }

    public function view(User $user, IpPublik $ipPublik): bool
    {
        return $user->can('ip_publik.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('ip_publik.buat');
    }

    public function update(User $user, IpPublik $ipPublik): bool
    {
        return $user->can('ip_publik.ubah');
    }

    public function delete(User $user, IpPublik $ipPublik): bool
    {
        return $user->can('ip_publik.hapus');
    }
}
