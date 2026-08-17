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
        Schema::create('paket_layanan', function (Blueprint $table) {
            $table->id();
            $table->string('nama_paket')->unique();
            $table->foreignId('profil_bandwidth_id')->constrained('profil_bandwidth');
            $table->decimal('harga', 12, 2);
            $table->unsignedTinyInteger('masa_aktif_nilai');
            $table->string('masa_aktif_satuan')->default('bulan');
            $table->text('keterangan')->nullable();
            $table->string('status')->default('aktif')->index();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paket_layanan');
    }
};
