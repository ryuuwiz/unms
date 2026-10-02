<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pelanggan tidak pernah transfer manual ke rekening dan NPWP tidak dipakai di mana pun
     * (CONTEXT.md "Profil Perusahaan").
     */
    public function up(): void
    {
        Schema::table('perusahaan', function (Blueprint $table) {
            $table->dropColumn(['npwp', 'nama_bank', 'nomor_rekening', 'atas_nama']);
        });
    }

    public function down(): void
    {
        Schema::table('perusahaan', function (Blueprint $table) {
            $table->string('npwp')->nullable()->after('website');
            $table->string('nama_bank')->nullable()->after('npwp');
            $table->string('nomor_rekening')->nullable()->after('nama_bank');
            $table->string('atas_nama')->nullable()->after('nomor_rekening');
        });
    }
};
