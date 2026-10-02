<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pelunasan Susulan dihapus: pembayaran gateway yang tidak bisa dilunasi otomatis kini
     * diperiksa dan diproses manual oleh staf (ADR-0069 superseded). Tabel kasusnya tidak dipakai lagi.
     */
    public function up(): void
    {
        Schema::dropIfExists('kasus_pelunasan_susulan');
    }

    /**
     * Tidak dibuat ulang: fiturnya sudah dihapus, dan migrasi pembuatnya tetap ada di riwayat.
     */
    public function down(): void {}
};
