<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IP Pool tidak lagi dipilih per layanan: layanan PPPoE dinamis memakai Rantai IP Pool Router (ADR-0060).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('layanan_pelanggan', function (Blueprint $table) {
            $table->dropForeign(['ip_pool_id']);
            $table->dropColumn('ip_pool_id');
        });
    }

    public function down(): void
    {
        Schema::table('layanan_pelanggan', function (Blueprint $table) {
            $table->foreignId('ip_pool_id')->nullable()->after('router_id')->constrained('ip_pool')->nullOnDelete();
        });

        // Isi dengan kepala rantai (pool terlama) router masing-masing layanan PPPoE.
        DB::table('layanan_pelanggan')
            ->where('jenis_koneksi', 'pppoe')
            ->whereNotNull('router_id')
            ->update(['ip_pool_id' => DB::raw('(SELECT MIN(ip_pool.id) FROM ip_pool WHERE ip_pool.router_id = layanan_pelanggan.router_id)')]);
    }
};
