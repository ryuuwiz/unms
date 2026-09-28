<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mengikuti hardening yang sama dengan `2026_09_12_100000_harden_pembayaran_table_soft_delete_and_restrict.php`:
     * cascadeOnDelete di sini memungkinkan forceDelete invoice menghancurkan riwayat transaksi payment
     * gateway tanpa jejak. Lihat ADR-0064.
     */
    public function up(): void
    {
        Schema::table('transaksi_payment_gateway', function (Blueprint $table) {
            $table->dropForeign('transaksi_payment_gateway_invoice_id_foreign');
        });

        Schema::table('transaksi_payment_gateway', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoice')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_payment_gateway', function (Blueprint $table) {
            $table->dropForeign('transaksi_payment_gateway_invoice_id_foreign');
        });

        Schema::table('transaksi_payment_gateway', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoice')->cascadeOnDelete();
        });
    }
};
