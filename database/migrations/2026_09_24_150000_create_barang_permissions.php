<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const SEMUA = ['barang.lihat', 'barang.buat', 'barang.ubah', 'barang.hapus', 'barang.masuk', 'barang.keluar'];

    /**
     * Izin Inventaris Barang (ADR-0057). Seeder peran juga mendefinisikannya, tetapi migrasi ini
     * menjamin izin ada meski RUN_ROLE_SEED=false di entrypoint.
     */
    public function up(): void
    {
        foreach (self::SEMUA as $nama) {
            Permission::findOrCreate($nama, 'web');
        }

        foreach (['admin', 'noc'] as $peran) {
            Role::where('name', $peran)->first()?->givePermissionTo(self::SEMUA);
        }

        Role::where('name', 'teknisi')->first()?->givePermissionTo('barang.lihat');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', self::SEMUA)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
