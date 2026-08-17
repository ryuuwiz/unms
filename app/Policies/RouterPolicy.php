<?php

namespace App\Policies;

use App\Models\Router;
use App\Models\User;

class RouterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('router.lihat');
    }

    public function view(User $user, Router $router): bool
    {
        return $user->can('router.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('router.buat');
    }

    public function update(User $user, Router $router): bool
    {
        return $user->can('router.ubah');
    }

    public function delete(User $user, Router $router): bool
    {
        return $user->can('router.hapus');
    }

    /**
     * Apakah user bisa melakukan provisioning/sync ke RouterOS.
     */
    public function provision(User $user, Router $router): bool
    {
        return $user->can('router.provision');
    }
}
