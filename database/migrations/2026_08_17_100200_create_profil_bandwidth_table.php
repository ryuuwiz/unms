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
        Schema::create('profil_bandwidth', function (Blueprint $table) {
            $table->id();
            $table->string('nama_bandwidth')->unique();
            // Kecepatan maksimum wajib diisi (dalam Kbps)
            $table->unsignedBigInteger('max_limit_tx');
            $table->unsignedBigInteger('max_limit_rx');
            // Field burst — semua nullable, jika null tidak di-provisioning ke RouterOS
            $table->unsignedBigInteger('burst_rate_tx')->nullable();
            $table->unsignedBigInteger('burst_rate_rx')->nullable();
            $table->unsignedBigInteger('burst_threshold_tx')->nullable();
            $table->unsignedBigInteger('burst_threshold_rx')->nullable();
            $table->unsignedInteger('burst_time_tx')->nullable()->comment('detik');
            $table->unsignedInteger('burst_time_rx')->nullable()->comment('detik');
            $table->unsignedBigInteger('limit_rate_tx')->nullable();
            $table->unsignedBigInteger('limit_rate_rx')->nullable();
            $table->tinyInteger('priority')->default(8)->comment('1=highest, 8=lowest');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profil_bandwidth');
    }
};
