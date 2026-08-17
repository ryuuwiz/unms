<?php

namespace App\Policies;

use App\Models\Package;
use App\Models\User;

class PackagePolicy
{
    /**
     * Determine whether the user can view any packages.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_packages');
    }

    /**
     * Determine whether the user can view the package.
     */
    public function view(User $user, Package $package): bool
    {
        return $user->can('view_packages');
    }

    /**
     * Determine whether the user can create packages.
     */
    public function create(User $user): bool
    {
        return $user->can('manage_packages');
    }

    /**
     * Determine whether the user can update the package.
     */
    public function update(User $user, Package $package): bool
    {
        return $user->can('manage_packages');
    }

    /**
     * Determine whether the user can delete the package.
     */
    public function delete(User $user, Package $package): bool
    {
        return $user->can('manage_packages');
    }
}
