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
        Schema::create('sysblas', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('provider')->default('wablas')->index();
            $table->string('nomor')->nullable()->comment('Nomor WhatsApp atau sender identity');
            $table->string('url_api')->default('https://tegal.wablas.com')->comment('Host Base URL API Gateway');
            $table->string('api_token')->comment('Token / API Key Gateway');
            $table->string('api_secret')->nullable()->comment('Secret Key Gateway (opsional)');
            $table->unsignedInteger('limit_per_menit')->default(25)->comment('Batas pesan keluar per menit');
            $table->boolean('is_default')->default(false)->index()->comment('Penanda koneksi default pengiriman sistem');
            $table->boolean('is_aktif')->default(true)->index()->comment('Status aktifasi koneksi');
            $table->text('keterangan')->nullable()->comment('Catatan atau peruntukan koneksi');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sysblas');
    }
};
