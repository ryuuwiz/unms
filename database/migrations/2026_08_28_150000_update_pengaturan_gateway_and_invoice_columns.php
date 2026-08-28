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
        // 1. Modifikasi tabel pengaturan_gateway
        Schema::table('pengaturan_gateway', function (Blueprint $table) {
            if (Schema::hasColumn('pengaturan_gateway', 'gateway')) {
                // Drop unique constraint on gateway column jika ada
                try {
                    $table->dropUnique(['gateway']);
                } catch (Throwable) {
                    // Ignore if unique index name differs
                }
            }

            if (! Schema::hasColumn('pengaturan_gateway', 'provider')) {
                $table->string('provider', 50)->default('xendit')->after('id');
            }

            if (! Schema::hasColumn('pengaturan_gateway', 'nama')) {
                $table->string('nama', 100)->default('Xendit Gateway')->after('provider');
            }

            if (! Schema::hasColumn('pengaturan_gateway', 'credentials')) {
                $table->text('credentials')->nullable()->after('nama');
            }

            if (! Schema::hasColumn('pengaturan_gateway', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('bebankan_ke_pelanggan');
            }

            if (! Schema::hasColumn('pengaturan_gateway', 'keterangan')) {
                $table->text('keterangan')->nullable()->after('sandbox_mode');
            }
        });

        // Pastikan record xendit pertama memiliki is_default = true jika belum ada
        DB::table('pengaturan_gateway')->where('provider', 'xendit')->orWhere('gateway', 'xendit')->update([
            'provider' => 'xendit',
            'nama' => 'Xendit Hosted Invoice',
            'is_default' => true,
        ]);

        // 2. Modifikasi tabel invoice untuk kolom payment gateway generik
        Schema::table('invoice', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice', 'payment_gateway_url')) {
                $table->text('payment_gateway_url')->nullable()->after('tanggal_lunas');
            }
            if (! Schema::hasColumn('invoice', 'payment_gateway_id')) {
                $table->string('payment_gateway_id', 100)->nullable()->after('payment_gateway_url');
            }
            if (! Schema::hasColumn('invoice', 'payment_gateway_provider')) {
                $table->string('payment_gateway_provider', 50)->nullable()->after('payment_gateway_id');
            }
            if (! Schema::hasColumn('invoice', 'payment_gateway_status')) {
                $table->string('payment_gateway_status', 30)->nullable()->after('payment_gateway_provider');
            }
            if (! Schema::hasColumn('invoice', 'payment_gateway_expired_at')) {
                $table->timestamp('payment_gateway_expired_at')->nullable()->after('payment_gateway_status');
            }
        });

        // Sinkronkan data eksisting xendit_* ke payment_gateway_*
        DB::table('invoice')->whereNotNull('xendit_invoice_url')->update([
            'payment_gateway_url' => DB::raw('xendit_invoice_url'),
            'payment_gateway_id' => DB::raw('xendit_invoice_id'),
            'payment_gateway_provider' => 'xendit',
            'payment_gateway_status' => DB::raw('xendit_status'),
            'payment_gateway_expired_at' => DB::raw('xendit_expired_at'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice', function (Blueprint $table) {
            $table->dropColumn([
                'payment_gateway_url',
                'payment_gateway_id',
                'payment_gateway_provider',
                'payment_gateway_status',
                'payment_gateway_expired_at',
            ]);
        });

        Schema::table('pengaturan_gateway', function (Blueprint $table) {
            $table->dropColumn([
                'provider',
                'nama',
                'credentials',
                'is_default',
                'keterangan',
            ]);
        });
    }
};
