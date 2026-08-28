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
        Schema::create('antrian_wa_blast', function (Blueprint $table) {
            $table->id();
            $table->string('no_hp_tujuan')->index();
            $table->text('pesan');
            $table->string('jenis')->index()->comment('Jenis notifikasi, misal: pengingat_tagihan_h3, tiket_status_update');
            $table->string('referensi_tipe')->nullable()->index();
            $table->unsignedBigInteger('referensi_id')->nullable()->index();
            $table->date('tanggal_kirim')->index()->comment('Tanggal target pengiriman untuk penjaminan idempotensi harian');
            $table->string('status')->default('menunggu')->index()->comment('menunggu, diproses, terkirim, gagal');
            $table->timestamp('dijadwalkan_pada')->nullable()->index();
            $table->timestamp('dikirim_pada')->nullable();
            $table->json('response_log')->nullable();
            $table->text('pesan_error')->nullable();
            $table->unsignedSmallInteger('percobaan_ke')->default(0);
            $table->timestamps();

            $table->unique(
                ['referensi_tipe', 'referensi_id', 'jenis', 'tanggal_kirim'],
                'antrian_wa_idempotency_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('antrian_wa_blast');
    }
};
