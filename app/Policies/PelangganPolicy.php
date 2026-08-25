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

    /**
     * Determine whether the user can view the customer's KTP image.
     * Sales can only view KTP for customers they created.
     */
    public function viewKtp(User $user, Pelanggan $pelanggan): bool
    {
        if (! $user->can('pelanggan.lihat_ktp')) {
            return false;
        }

        if ($user->hasRole('sales')) {
            return $pelanggan->dibuat_oleh === $user->id;
        }

        return true;
    }

    /**
     * Determine whether the user can view the customer's documents.
     * Sales can only view documents for customers they created.
     */
    public function viewDokumen(User $user, Pelanggan $pelanggan): bool
    {
        if (! $user->can('pelanggan.lihat_dokumen')) {
            return false;
        }

        if ($user->hasRole('sales')) {
            return $pelanggan->dibuat_oleh === $user->id;
        }

        return true;
    }

    /**
     * Determine whether the user can upload documents for the customer.
     * Sales can only upload documents for customers they created.
     */
    public function uploadDokumen(User $user, Pelanggan $pelanggan): bool
    {
        if (! $user->can('pelanggan.unggah_dokumen')) {
            return false;
        }

        if ($user->hasRole('sales')) {
            return $pelanggan->dibuat_oleh === $user->id;
        }

        return true;
    }

    /**
     * Determine whether the user can delete documents for the customer.
     * Sales can only delete documents for customers they created.
     */
    public function deleteDokumen(User $user, Pelanggan $pelanggan): bool
    {
        if (! $user->can('pelanggan.hapus_dokumen')) {
            return false;
        }

        if ($user->hasRole('sales')) {
            return $pelanggan->dibuat_oleh === $user->id;
        }

        return true;
    }
}
