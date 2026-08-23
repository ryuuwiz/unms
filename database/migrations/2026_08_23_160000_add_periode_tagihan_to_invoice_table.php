<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoice', function (Blueprint $table) {
            $table->string('periode_tagihan', 7)->nullable()->after('no_invoice')->comment('Periode tagihan format YYYY-MM');
            $table->index(['layanan_pelanggan_id', 'periode_tagihan']);
        });

        // Backfill data eksisting dari tanggal_terbit / tanggal_jatuh_tempo (cross-database compatible)
        if (DB::getDriverName() === 'sqlite') {
            DB::table('invoice')
                ->whereNull('periode_tagihan')
                ->update([
                    'periode_tagihan' => DB::raw("strftime('%Y-%m', tanggal_terbit)"),
                ]);
        } else {
            DB::table('invoice')
                ->whereNull('periode_tagihan')
                ->update([
                    'periode_tagihan' => DB::raw("DATE_FORMAT(tanggal_terbit, '%Y-%m')"),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice', function (Blueprint $table) {
            $table->dropIndex(['layanan_pelanggan_id', 'periode_tagihan']);
            $table->dropColumn('periode_tagihan');
        });
    }
};
