<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'layanan_pelanggan.lihat_ppp_password';

    private const ROLES = ['teknisi', 'noc'];

    /**
     * Seeder tidak menjangkau DB produksi -- lihat ADR-0055.
     */
    public function up(): void
    {
        $permission = Permission::findOrCreate(self::PERMISSION, 'web');

        foreach (Role::whereIn('name', self::ROLES)->get() as $role) {
            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (Role::whereIn('name', self::ROLES)->get() as $role) {
            $role->revokePermissionTo(self::PERMISSION);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
