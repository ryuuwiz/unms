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
        Schema::table('pengaturan_gateway', function (Blueprint $table) {
            $table->decimal('fee_va_nominal', 12, 2)->default(4000.00)->change();
            $table->decimal('fee_qris_persen', 5, 2)->default(0.70)->change();
            $table->boolean('bebankan_ke_pelanggan')->default(true)->change();
        });

        // Perbarui data yang masih bernilai 0 atau belum dibebankan ke pelanggan dengan standar fee payment gateway
        DB::table('pengaturan_gateway')
            ->where('fee_va_nominal', 0)
            ->update(['fee_va_nominal' => 4000.00]);

        DB::table('pengaturan_gateway')
            ->where('fee_qris_persen', 0)
            ->update(['fee_qris_persen' => 0.70]);

        DB::table('pengaturan_gateway')
            ->where('bebankan_ke_pelanggan', false)
            ->update(['bebankan_ke_pelanggan' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pengaturan_gateway', function (Blueprint $table) {
            $table->decimal('fee_va_nominal', 12, 2)->default(0)->change();
            $table->decimal('fee_qris_persen', 5, 2)->default(0)->change();
            $table->boolean('bebankan_ke_pelanggan')->default(false)->change();
        });
    }
};
