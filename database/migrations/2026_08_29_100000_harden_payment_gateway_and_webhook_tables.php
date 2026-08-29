<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Modifikasi tabel webhook_log
        Schema::table('webhook_log', function (Blueprint $table) {
            if (! Schema::hasColumn('webhook_log', 'provider')) {
                $table->string('provider', 50)->default('xendit')->after('id');
            }
            if (! Schema::hasColumn('webhook_log', 'provider_event_id')) {
                $table->string('provider_event_id', 150)->nullable()->after('event_type');
            }
        });

        // Sinkronisasi data awal webhook_log
        DB::table('webhook_log')->whereNotNull('xendit_event_id')->whereNull('provider_event_id')->update([
            'provider_event_id' => DB::raw('xendit_event_id'),
            'provider' => 'xendit',
        ]);

        // Safe deduplikasi webhook_log sebelum menambahkan unique index
        $duplicateWebhookLogs = DB::table('webhook_log')
            ->select('provider', 'provider_event_id', DB::raw('COUNT(*) as count'), DB::raw('MAX(id) as max_id'))
            ->whereNotNull('provider_event_id')
            ->where('provider_event_id', '!=', '')
            ->groupBy('provider', 'provider_event_id')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicateWebhookLogs as $dup) {
            DB::table('webhook_log')
                ->where('provider', $dup->provider)
                ->where('provider_event_id', $dup->provider_event_id)
                ->where('id', '<', $dup->max_id)
                ->delete();
        }

        Schema::table('webhook_log', function (Blueprint $table) {
            $table->unique(['provider', 'provider_event_id'], 'webhook_log_provider_event_unique');
        });

        // 2. Modifikasi tabel transaksi_payment_gateway
        Schema::table('transaksi_payment_gateway', function (Blueprint $table) {
            if (! Schema::hasColumn('transaksi_payment_gateway', 'provider_reference_id')) {
                $table->string('provider_reference_id', 100)->nullable()->after('external_id');
            }
        });

        // Sinkronisasi data awal transaksi_payment_gateway
        DB::table('transaksi_payment_gateway')->whereNotNull('xendit_reference_id')->whereNull('provider_reference_id')->update([
            'provider_reference_id' => DB::raw('xendit_reference_id'),
        ]);

        // Safe deduplikasi transaksi_payment_gateway sebelum unique index
        $duplicateTrx = DB::table('transaksi_payment_gateway')
            ->select('gateway', 'provider_reference_id', DB::raw('COUNT(*) as count'), DB::raw('MAX(id) as max_id'))
            ->whereNotNull('provider_reference_id')
            ->where('provider_reference_id', '!=', '')
            ->groupBy('gateway', 'provider_reference_id')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicateTrx as $dup) {
            DB::table('transaksi_payment_gateway')
                ->where('gateway', $dup->gateway)
                ->where('provider_reference_id', $dup->provider_reference_id)
                ->where('id', '<', $dup->max_id)
                ->delete();
        }

        Schema::table('transaksi_payment_gateway', function (Blueprint $table) {
            $table->unique(['gateway', 'provider_reference_id'], 'trx_gateway_provider_ref_unique');
        });

        // 3. Modifikasi tabel pembayaran: Safe deduplikasi & unique constraint
        $duplicatePembayaran = DB::table('pembayaran')
            ->select('metode', 'referensi_transaksi', DB::raw('COUNT(*) as count'), DB::raw('MAX(id) as max_id'))
            ->whereNotNull('referensi_transaksi')
            ->where('referensi_transaksi', '!=', '')
            ->groupBy('metode', 'referensi_transaksi')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicatePembayaran as $dup) {
            DB::table('pembayaran')
                ->where('metode', $dup->metode)
                ->where('referensi_transaksi', $dup->referensi_transaksi)
                ->where('id', '<', $dup->max_id)
                ->delete();
        }

        Schema::table('pembayaran', function (Blueprint $table) {
            $table->unique(['metode', 'referensi_transaksi'], 'pembayaran_metode_referensi_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->dropUnique('pembayaran_metode_referensi_unique');
        });

        Schema::table('transaksi_payment_gateway', function (Blueprint $table) {
            $table->dropUnique('trx_gateway_provider_ref_unique');
            $table->dropColumn('provider_reference_id');
        });

        Schema::table('webhook_log', function (Blueprint $table) {
            $table->dropUnique('webhook_log_provider_event_unique');
            $table->dropColumn(['provider', 'provider_event_id']);
        });
    }
};
