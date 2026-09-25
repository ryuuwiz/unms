<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const SEMUA = ['rab.lihat', 'rab.kelola', 'rab.buka_kunci'];

    /**
     * Izin RAB Kantor. Seeder peran juga mendefinisikannya, tetapi migrasi ini
     * menjamin izin ada meski RUN_ROLE_SEED=false di entrypoint.
     */
    public function up(): void
    {
        foreach (self::SEMUA as $nama) {
            Permission::findOrCreate($nama, 'web');
        }

        Role::where('name', 'admin')->first()?->givePermissionTo(['rab.lihat', 'rab.kelola']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', self::SEMUA)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
