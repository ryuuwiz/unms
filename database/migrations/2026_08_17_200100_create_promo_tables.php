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
        Schema::create('promo', function (Blueprint $table) {
            $table->id();
            $table->string('kode_promo', 50)->unique();
            $table->string('nama_promo', 100);
            $table->string('jenis', 30); // diskon, bonus_durasi
            $table->text('deskripsi')->nullable();
            $table->text('aturan')->nullable();
            $table->unsignedInteger('bayar_bulan')->nullable();
            $table->unsignedInteger('bonus_bulan')->nullable();
            $table->string('diskon_tipe', 20)->nullable(); // persentase, nominal
            $table->decimal('diskon_nilai', 12, 2)->nullable();
            $table->decimal('minimal_nominal_invoice', 12, 2)->nullable();
            $table->unsignedInteger('kuota_global')->nullable();
            $table->unsignedInteger('kuota_per_pelanggan')->nullable();
            $table->unsignedInteger('terpakai_global')->default(0);
            $table->date('berlaku_dari')->nullable();
            $table->date('berlaku_sampai')->nullable();
            $table->boolean('aktif')->default(true);
            $table->boolean('tampil_ke_customer')->default(true);
            $table->timestamps();
        });

        Schema::create('promo_penggunaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_id')->constrained('promo')->cascadeOnDelete();
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
            $table->unsignedBigInteger('invoice_id'); // FK added in invoice migration or after
            $table->dateTime('digunakan_pada');
            $table->timestamps();

            $table->unique(['promo_id', 'invoice_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_penggunaan');
        Schema::dropIfExists('promo');
    }
};
