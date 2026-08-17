<?php

namespace App\Policies;

use App\Models\Pelanggan;
use App\Models\User;

class PelangganPolicy
{
    /**
     * Determine whether the user can view any pelanggan.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('pelanggan.lihat');
    }

    /**
     * Determine whether the user can view the pelanggan.
     */
    public function view(User $user, Pelanggan $pelanggan): bool
    {
        return $user->can('pelanggan.lihat');
    }

    /**
     * Determine whether the user can create pelanggan.
     */
    public function create(User $user): bool
    {
        return $user->can('pelanggan.buat');
    }

    /**
     * Determine whether the user can update the pelanggan.
     * Sales can only update customers they created.
     */
    public function update(User $user, Pelanggan $pelanggan): bool
    {
        if (! $user->can('pelanggan.ubah')) {
            return false;
        }

        if ($user->hasRole('sales')) {
            return $pelanggan->dibuat_oleh === $user->id;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the pelanggan.
     * Sales can only delete customers they created.
     */
    public function delete(User $user, Pelanggan $pelanggan): bool
    {
        if (! $user->can('pelanggan.hapus')) {
            return false;
        }

        if ($user->hasRole('sales')) {
            return $pelanggan->dibuat_oleh === $user->id;
        }

        return true;
    }
}
