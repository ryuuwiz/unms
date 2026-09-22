<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('layanan_pelanggan', function (Blueprint $table) {
            $table->string('price_mode')->default('paket')->after('paket_layanan_id')
                ->comment('paket = ikut harga PaketLayanan saat ini, custom = pakai price_custom tetap');
            $table->decimal('price_custom', 12, 2)->nullable()->after('price_mode')
                ->comment('Harga dasar bulanan tetap, dipakai saat price_mode = custom');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('layanan_pelanggan', function (Blueprint $table) {
            $table->dropColumn(['price_mode', 'price_custom']);
        });
    }
};
