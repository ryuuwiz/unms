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
            $table->string('nama_site')->nullable()->after('site_id')->comment('Label/identitas lokasi spesifik (misal: Rumah Utama, Ruko Thamrin, dsb)');
            $table->text('alamat_pemasangan')->nullable()->after('odp_port_id')->comment('Alamat fisik spesifik titik instalasi jika berbeda dari alamat pelanggan');
            $table->decimal('latitude', 10, 7)->nullable()->after('alamat_pemasangan')->comment('Titik koordinat latitude instalasi site');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude')->comment('Titik koordinat longitude instalasi site');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('layanan_pelanggan', function (Blueprint $table) {
            $table->dropColumn([
                'nama_site',
                'alamat_pemasangan',
                'latitude',
                'longitude',
            ]);
        });
    }
};
