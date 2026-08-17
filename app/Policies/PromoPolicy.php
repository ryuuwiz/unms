<?php

namespace App\Policies;

use App\Models\Promo;
use App\Models\User;

class PromoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('promo.lihat');
    }

    public function view(User $user, Promo $promo): bool
    {
        return $user->can('promo.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('promo.buat');
    }

    public function update(User $user, Promo $promo): bool
    {
        return $user->can('promo.ubah');
    }

    public function delete(User $user, Promo $promo): bool
    {
        return $user->can('promo.hapus');
    }
}
