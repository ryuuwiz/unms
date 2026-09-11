<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Naikkan default jeda antar pesan ke 300 detik (5 menit) sebagai proteksi anti-ban
     * WhatsApp default untuk koneksi baru — sebelumnya default 3 detik terlalu agresif untuk
     * gateway self-hosted (GOWA/WAHA) yang memakai nomor pribadi via whatsmeow.
     */
    public function up(): void
    {
        Schema::table('sysblas', function (Blueprint $table) {
            $table->unsignedInteger('delay_detik')->default(300)->comment('Jeda minimal antar pesan dalam detik')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sysblas', function (Blueprint $table) {
            $table->unsignedInteger('delay_detik')->default(3)->comment('Jeda minimal antar pesan dalam detik')->change();
        });
    }
};
