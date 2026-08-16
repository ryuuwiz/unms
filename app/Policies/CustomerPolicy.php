<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Determine whether the user can view any customers.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_customers');
    }

    /**
     * Determine whether the user can view the customer.
     */
    public function view(User $user, Customer $customer): bool
    {
        return $user->can('view_customers');
    }

    /**
     * Determine whether the user can create customers.
     */
    public function create(User $user): bool
    {
        return $user->can('manage_customers');
    }

    /**
     * Determine whether the user can update the customer.
     * Super admin and admin can update all; sales can only update customers they created.
     */
    public function update(User $user, Customer $customer): bool
    {
        if (! $user->can('manage_customers')) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        return $customer->created_by === $user->id;
    }

    /**
     * Determine whether the user can delete/archive the customer.
     * Super admin and admin can delete all; sales can only delete customers they created.
     */
    public function delete(User $user, Customer $customer): bool
    {
        if (! $user->can('manage_customers')) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        return $customer->created_by === $user->id;
    }
}
