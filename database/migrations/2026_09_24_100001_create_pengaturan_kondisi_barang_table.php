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
        Schema::create('pengaturan_kondisi_barang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique()->comment('Kode kondisi barang, contoh: NEW (Baru), PGT (Pengganti)');
            $table->string('nama');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengaturan_kondisi_barang');
    }
};
