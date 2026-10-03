<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Xendit dipensiunkan (issue #76, ADR-0072): kolom kompatibilitas xendit_* dan fee khusus Xendit
 * dihapus. Nilainya sudah tersalin ke kolom payment_gateway_* / provider_* oleh migrasi
 * 2026_08_28 dan 2026_08_29. Koneksi Xendit dibiarkan sebagai riwayat transaksi lama, tetapi
 * dinonaktifkan agar tidak pernah terpilih sebagai gateway default.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pengaturan_gateway')->where('provider', 'xendit')->update(['is_active' => false, 'is_default' => false]);

        Schema::table('invoice', function (Blueprint $table) {
            $table->dropIndex(['xendit_status', 'xendit_expired_at']);
            $table->dropColumn(['xendit_invoice_id', 'xendit_invoice_url', 'xendit_status', 'xendit_expired_at']);
        });

        Schema::table('transaksi_payment_gateway', function (Blueprint $table) {
            $table->dropColumn('xendit_reference_id');
        });

        Schema::table('webhook_log', function (Blueprint $table) {
            $table->dropIndex(['xendit_event_id']);
            $table->dropColumn('xendit_event_id');
        });

        Schema::table('pengaturan_gateway', function (Blueprint $table) {
            $table->dropColumn(['fee_va_nominal', 'fee_qris_persen', 'fee_qris_nominal']);
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_gateway', function (Blueprint $table) {
            $table->decimal('fee_va_nominal', 12, 2)->default(4000.00);
            $table->decimal('fee_qris_persen', 5, 2)->default(0.70);
            $table->decimal('fee_qris_nominal', 12, 2)->default(0);
        });

        Schema::table('webhook_log', function (Blueprint $table) {
            $table->string('xendit_event_id', 150)->nullable()->index();
        });

        Schema::table('transaksi_payment_gateway', function (Blueprint $table) {
            $table->string('xendit_reference_id', 100)->nullable();
        });

        Schema::table('invoice', function (Blueprint $table) {
            $table->string('xendit_invoice_id', 100)->nullable();
            $table->text('xendit_invoice_url')->nullable();
            $table->string('xendit_status', 30)->nullable();
            $table->timestamp('xendit_expired_at')->nullable();
            $table->index(['xendit_status', 'xendit_expired_at']);
        });
    }
};
