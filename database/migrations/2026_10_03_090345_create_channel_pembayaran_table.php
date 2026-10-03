<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Channel Pembayaran yang dipilih pelanggan di portal (ADR-0073). Sengaja dimulai kosong:
 * tanpa channel ON, portal tetap memakai Hosted Invoice iPaymu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengaturan_gateway_id')->constrained('pengaturan_gateway')->cascadeOnDelete();
            $table->string('tipe', 30);
            $table->string('kode', 50);
            $table->decimal('fee_admin', 12, 2)->default(0);
            $table->boolean('fee_persen')->default(false);
            $table->string('icon_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('keterangan', 500)->nullable();
            $table->timestamps();

            $table->unique(['pengaturan_gateway_id', 'kode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_pembayaran');
    }
};
