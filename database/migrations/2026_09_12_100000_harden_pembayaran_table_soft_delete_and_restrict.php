<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `pembayaran` sebelumnya tidak punya soft delete dan `invoice_id` memakai
     * cascadeOnDelete -- force delete atau `DELETE` mentah pada `invoice` menghancurkan
     * catatan keuangan pelanggan secara permanen tanpa jejak. Soft delete invoice sendiri
     * sudah aman (tidak menyentuh baris pembayaran via FK), jadi perubahan ini hanya
     * mengunci jalur penghapusan paksa yang sesungguhnya berbahaya.
     */
    public function up(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('pembayaran', function (Blueprint $table) {
            $table->dropForeign('pembayaran_invoice_id_foreign');
        });

        Schema::table('pembayaran', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoice')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->dropForeign('pembayaran_invoice_id_foreign');
        });

        Schema::table('pembayaran', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoice')->cascadeOnDelete();
        });

        Schema::table('pembayaran', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
