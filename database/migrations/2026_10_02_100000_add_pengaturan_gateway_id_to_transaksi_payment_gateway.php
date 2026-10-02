<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Koneksi Payment Gateway yang menerbitkan transaksi (ADR-0067). Callback dan cek status
 * transaksi diverifikasi dengan kredensial koneksi ini. Baris lama dibiarkan null dan
 * jatuh ke koneksi aktif & default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_payment_gateway', function (Blueprint $table) {
            $table->foreignId('pengaturan_gateway_id')
                ->nullable()
                ->after('gateway')
                ->constrained('pengaturan_gateway')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_payment_gateway', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pengaturan_gateway_id');
        });
    }
};
