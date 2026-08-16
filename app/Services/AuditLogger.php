<?php

namespace App\Services;

use App\Models\AuditLog;
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
}
