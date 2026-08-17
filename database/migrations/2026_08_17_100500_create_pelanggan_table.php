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
        Schema::create('pelanggan', function (Blueprint $table) {
            $table->id();
            $table->string('no_reg')->unique()->comment('Format: REG-YYYY-NNNNNN');
            $table->string('tipe_pelanggan')->default('rumah');
            $table->text('nik')->nullable()->comment('Encrypted via Laravel encrypted cast');
            $table->string('nama_depan');
            $table->string('nama_belakang')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('no_hp')->index();
            $table->string('telepon_rumah')->nullable();
            $table->foreignId('perumahan_id')->nullable()->constrained('perumahan')->nullOnDelete();
            $table->string('rt')->nullable();
            $table->string('rw')->nullable();
            $table->string('no_rumah')->nullable();
            $table->string('kode_pos', 5)->nullable();
            $table->text('alamat_lengkap');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status')->default('prospek')->index();
            $table->string('kode_pembayaran', 12)->unique()->comment('Identifier unik untuk Virtual Account Xendit');
            $table->string('gambar_ktp_path')->nullable();
            $table->foreignId('dibuat_oleh')->constrained('users');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'no_hp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pelanggan');
    }
};
