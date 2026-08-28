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
        Schema::create('aturan_pengingat_tagihan', function (Blueprint $table) {
            $table->id();
            $table->string('nama_aturan');
            $table->string('tipe_pengingat')->default('sebelum_jatuh_tempo')->index();
            $table->integer('hari_offset')->default(3)->comment('Offset hari relatif terhadap tanggal jatuh tempo. Contoh: 3 = H-3, 0 = Hari H, 3 = H+3');
            $table->time('jam_eksekusi')->default('08:30:00')->comment('Jam eksekusi harian pengiriman notifikasi');
            $table->foreignId('template_id')->constrained('wa_template')->cascadeOnUpdate()->restrictOnDelete();
            $table->boolean('kirim_ulang_berkala')->default(false)->comment('Apakah pengingat diulang berkala jika belum lunas');
            $table->unsignedInteger('interval_hari')->nullable()->comment('Interval hari untuk kirim ulang pengingat tunggakan');
            $table->boolean('is_aktif')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aturan_pengingat_tagihan');
    }
};
