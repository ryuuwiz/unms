<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kasus Pelunasan Susulan (CONTEXT.md): pembayaran gateway yang perlu tindakan staf. Satu kasus
     * per pembayaran + alasan agar sapuan berulang tidak menggandakan laporan.
     */
    public function up(): void
    {
        Schema::create('kasus_pelunasan_susulan', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 191);
            $table->string('alasan', 500);
            $table->string('aksi', 30);
            $table->string('xendit_id')->nullable();
            $table->string('koneksi')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained('invoice')->nullOnDelete();
            $table->decimal('nominal', 15, 2)->nullable();
            $table->timestamp('dibayar_pada')->nullable();
            $table->string('status_invoice_saat_itu')->nullable();
            $table->timestamp('ditangani_pada')->nullable();
            $table->foreignId('ditangani_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan_penanganan')->nullable();
            $table->timestamps();

            $table->unique(['external_id', 'alasan']);
            $table->index('ditangani_pada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kasus_pelunasan_susulan');
    }
};
