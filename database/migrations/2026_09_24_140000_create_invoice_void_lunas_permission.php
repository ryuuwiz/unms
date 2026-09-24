<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Seeder tidak menjangkau DB produksi. Izin hanya untuk super_admin (lolos Gate::before);
     * peran lain diberi lewat halaman Peran.
     */
    public function up(): void
    {
        Permission::findOrCreate('invoice.void_lunas', 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'invoice.void_lunas')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
