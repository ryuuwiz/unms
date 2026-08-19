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
        Schema::create('ticket', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_ticket', 50)->unique();
            $table->string('jenis', 50); // pemasangan, pencabutan, gangguan, pindah_alamat
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->restrictOnDelete();
            $table->foreignId('layanan_pelanggan_id')->nullable()->constrained('layanan_pelanggan')->nullOnDelete();
            $table->string('prioritas', 30)->default('sedang'); // rendah, sedang, tinggi, darurat
            $table->string('divisi', 50)->default('teknisi'); // admin, customer_service, sales, noc, teknisi
            $table->foreignId('pic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('baru'); // baru, diproses, menunggu_konfirmasi, selesai, batal
            $table->string('sumber', 30)->default('manual'); // manual, sistem
            $table->dateTime('sla_target_selesai')->nullable();
            $table->boolean('perlu_aktivasi_manual')->default(false);
            $table->text('deskripsi');
            $table->dateTime('dijadwalkan_pada')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'jenis', 'prioritas']);
            $table->index(['pic_id', 'status']);
            $table->index(['pelanggan_id', 'status']);
            $table->index('sla_target_selesai');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket');
    }
};
