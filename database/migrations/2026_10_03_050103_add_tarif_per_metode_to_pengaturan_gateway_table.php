<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tarif Biaya Admin Gateway per metode bayar, pass-through termasuk PPN dan biaya
     * pemrosesan (ADR-0072). Nilai awal = harga publik Xendit per Okt 2026.
     */
    public function up(): void
    {
        Schema::table('pengaturan_gateway', function (Blueprint $table) {
            $table->decimal('fee_va_nominal', 12, 2)->default(9000.00)->change();
            $table->decimal('fee_va_persen', 5, 2)->default(0)->after('fee_va_nominal');
            $table->boolean('fee_va_termasuk_ppn')->default(false)->after('fee_va_persen');
            $table->boolean('fee_qris_termasuk_ppn')->default(true)->after('fee_qris_nominal');
            $table->decimal('biaya_pemrosesan', 12, 2)->default(4000.00)->after('fee_qris_termasuk_ppn');
            $table->decimal('ppn_persen', 5, 2)->default(11.00)->after('biaya_pemrosesan');
        });

        DB::table('pengaturan_gateway')->update([
            'fee_va_nominal' => 9000.00,
            'fee_qris_persen' => 0.70,
            'fee_qris_nominal' => 0.00,
        ]);
    }

    public function down(): void
    {
        Schema::table('pengaturan_gateway', function (Blueprint $table) {
            $table->decimal('fee_va_nominal', 12, 2)->default(4000.00)->change();
            $table->dropColumn(['fee_va_persen', 'fee_va_termasuk_ppn', 'fee_qris_termasuk_ppn', 'biaya_pemrosesan', 'ppn_persen']);
        });
    }
};
