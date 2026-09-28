<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * `invoice.hapus` sebenarnya cuma Pembatalan Invoice (status flip, bukan hapus sungguhan) --
     * diganti nama jadi `invoice.batalkan` supaya cocok dengan perilakunya. `invoice.hapus_permanen`
     * adalah kemampuan baru (lihat ADR-0064 & CONTEXT.md "Penghapusan Permanen Invoice"), hanya untuk
     * super_admin lewat Gate::before(), tidak perlu di-assign eksplisit ke role manapun.
     */
    public function up(): void
    {
        Permission::where('name', 'invoice.hapus')->where('guard_name', 'web')->update(['name' => 'invoice.batalkan']);

        Permission::findOrCreate('invoice.hapus_permanen', 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'invoice.hapus_permanen')->where('guard_name', 'web')->delete();

        Permission::where('name', 'invoice.batalkan')->where('guard_name', 'web')->update(['name' => 'invoice.hapus']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
