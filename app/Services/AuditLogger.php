<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\PackageStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\Request;
use Spatie\Permission\Models\Role;

class AuditLogger
{
    /**
     * Record a role change for a user.
     */
    public static function recordRoleChange(User $actor, User $subject, string $oldRole, string $newRole): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'role_changed',
            'subject_type' => User::class,
            'subject_id' => $subject->id,
            'old_values' => ['role' => $oldRole],
            'new_values' => ['role' => $newRole],
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Record the creation of a new role.
     */
    public static function recordRoleCreated(User $actor, Role $role): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'role_created',
            'subject_type' => Role::class,
            'subject_id' => $role->id,
            'old_values' => null,
            'new_values' => ['name' => $role->name],
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Record the deletion of a role.
     */
    public static function recordRoleDeleted(User $actor, Role $role): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'role_deleted',
            'subject_type' => Role::class,
            'subject_id' => $role->id,
            'old_values' => ['name' => $role->name],
            'new_values' => null,
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Record a change in the permissions assigned to a role.
     *
     * @param  array<string>  $oldPermissions
     * @param  array<string>  $newPermissions
     */
    public static function recordRolePermissionsUpdated(User $actor, Role $role, array $oldPermissions, array $newPermissions): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'role_permissions_updated',
            'subject_type' => Role::class,
            'subject_id' => $role->id,
            'old_values' => ['permissions' => $oldPermissions],
            'new_values' => ['permissions' => $newPermissions],
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Record an admin-initiated password reset for another user.
     */
    public static function recordPasswordReset(User $actor, User $subject): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'password_reset',
            'subject_type' => User::class,
            'subject_id' => $subject->id,
            'old_values' => null,
            'new_values' => null,
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Record the creation of a customer.
     */
    public static function recordCustomerCreated(User $actor, Customer $customer): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'customer_created',
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
            'old_values' => null,
            'new_values' => [
                'customer_code' => $customer->customer_code,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'status' => $customer->status->value,
            ],
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Record updates to a customer record.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public static function recordCustomerUpdated(User $actor, Customer $customer, array $oldValues, array $newValues): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'customer_updated',
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Record customer status changes.
     */
    public static function recordCustomerStatusChanged(User $actor, Customer $customer, CustomerStatus $oldStatus, CustomerStatus $newStatus): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'customer_status_changed',
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
            'old_values' => ['status' => $oldStatus->value],
            'new_values' => ['status' => $newStatus->value],
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Record customer deletion / archival.
     */
    public static function recordCustomerDeleted(User $actor, Customer $customer): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'customer_deleted',
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
            'old_values' => [
                'customer_code' => $customer->customer_code,
                'name' => $customer->name,
            ],
            'new_values' => null,
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Record the creation of an internet package.
     */
    public static function recordPackageCreated(User $actor, Package $package): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'package_created',
            'subject_type' => Package::class,
            'subject_id' => $package->id,
            'old_values' => null,
            'new_values' => [
                'name' => $package->name,
                'download_speed_mbps' => $package->download_speed_mbps,
                'upload_speed_mbps' => $package->upload_speed_mbps,
                'price' => $package->price,
                'status' => $package->status->value,
            ],
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Record updates to an internet package.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public static function recordPackageUpdated(User $actor, Package $package, array $oldValues, array $newValues): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'package_updated',
            'subject_type' => Package::class,
            'subject_id' => $package->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Record internet package status changes.
     */
    public static function recordPackageStatusChanged(User $actor, Package $package, PackageStatus $oldStatus, PackageStatus $newStatus): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'package_status_changed',
            'subject_type' => Package::class,
            'subject_id' => $package->id,
            'old_values' => ['status' => $oldStatus->value],
            'new_values' => ['status' => $newStatus->value],
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Record internet package deletion / archival.
     */
    public static function recordPackageDeleted(User $actor, Package $package): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'package_deleted',
            'subject_type' => Package::class,
            'subject_id' => $package->id,
            'old_values' => [
                'name' => $package->name,
                'price' => $package->price,
            ],
            'new_values' => null,
            'ip_address' => Request::ip(),
        ]);
    }
}
