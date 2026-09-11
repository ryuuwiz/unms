<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat nilai default lama, dipakai untuk backfill konservatif di bawah:
     * `limit_per_menit` pernah default 25 (migrasi awal) dan 60 (SysblasSeeder produksi);
     * `delay_detik` pernah default 3 (migrasi awal) dan 300 (migrasi 2026_09_11_181132).
     */
    private const LIMIT_PER_MENIT_DEFAULT_LAMA = [25, 60];

    private const DELAY_DETIK_DEFAULT_LAMA = [3, 300];

    /**
     * Run the migrations.
     *
     * Turunkan default Batas Laju Pengiriman & Jeda Antar-Pesan ke pasangan yang konsisten
     * (4 pesan/menit ~ jeda 15 detik) untuk keamanan anti-ban gateway self-hosted (WAHA/GOWA).
     * Backfill hanya menyentuh baris yang masih memakai nilai default lama — koneksi yang
     * sudah dikustomisasi admin ke nilai lain sengaja dibiarkan apa adanya (lihat ADR 0035).
     */
    public function up(): void
    {
        Schema::table('sysblas', function (Blueprint $table) {
            $table->unsignedInteger('limit_per_menit')->default(4)->comment('Batas pesan keluar per menit')->change();
            $table->unsignedInteger('delay_detik')->default(15)->comment('Jeda minimal antar pesan dalam detik')->change();
        });

        DB::table('sysblas')
            ->whereIn('limit_per_menit', self::LIMIT_PER_MENIT_DEFAULT_LAMA)
            ->update(['limit_per_menit' => 4]);

        DB::table('sysblas')
            ->whereIn('delay_detik', self::DELAY_DETIK_DEFAULT_LAMA)
            ->update(['delay_detik' => 15]);
    }

    /**
     * Reverse the migrations.
     *
     * Hanya mengembalikan default kolom; backfill data tidak di-reverse karena nilai lama
     * per-baris yang tertimpa sudah tidak dapat dibedakan dari kustomisasi admin.
     */
    public function down(): void
    {
        Schema::table('sysblas', function (Blueprint $table) {
            $table->unsignedInteger('limit_per_menit')->default(25)->comment('Batas pesan keluar per menit')->change();
            $table->unsignedInteger('delay_detik')->default(300)->comment('Jeda minimal antar pesan dalam detik')->change();
        });
    }
};
