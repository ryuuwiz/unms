<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inventaris IP Publik Dedicated. Status tersedia/terpakai diturunkan dari layanan_pelanggan_id.
     */
    public function up(): void
    {
        Schema::create('ip_publik', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('router')->cascadeOnDelete();
            $table->string('alamat_ip')->unique();
            $table->string('gateway');
            $table->decimal('harga_bulanan', 12, 2)->default(0);
            $table->decimal('harga_ditagih', 12, 2)->nullable();
            $table->foreignId('layanan_pelanggan_id')->nullable()->constrained('layanan_pelanggan')->nullOnDelete();
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_publik');
    }
};
