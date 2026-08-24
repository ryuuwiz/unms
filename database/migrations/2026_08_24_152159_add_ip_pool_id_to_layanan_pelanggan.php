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
        // Tambah kolom hanya jika belum ada (idempoten — dari percobaan partial migration).
        if (! Schema::hasColumn('layanan_pelanggan', 'ip_pool_id')) {
            Schema::table('layanan_pelanggan', function (Blueprint $table) {
                $table->foreignId('ip_pool_id')
                    ->nullable()
                    ->after('router_id')
                    ->constrained('ip_pool')
                    ->nullOnDelete();
            });
        } else {
            // Kolom sudah ada tapi mungkin tanpa FK — tambahkan FK saja.
            Schema::table('layanan_pelanggan', function (Blueprint $table) {
                $table->foreign('ip_pool_id')
                    ->references('id')
                    ->on('ip_pool')
                    ->nullOnDelete();
            });
        }

        // Auto-assign ip_pool_id untuk router yang hanya punya tepat 1 pool.
        // Router dengan >1 pool dibiarkan null — staf isi manual via form Edit.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('
                UPDATE layanan_pelanggan
                SET ip_pool_id = (
                    SELECT MIN(id)
                    FROM ip_pool
                    WHERE ip_pool.router_id = layanan_pelanggan.router_id
                    GROUP BY router_id
                    HAVING COUNT(*) = 1
                )
                WHERE ip_pool_id IS NULL
                  AND deleted_at IS NULL
            ');
        } else {
            DB::statement('
                UPDATE layanan_pelanggan lp
                INNER JOIN (
                    SELECT router_id, MIN(id) AS pool_id
                    FROM ip_pool
                    GROUP BY router_id
                    HAVING COUNT(*) = 1
                ) single_pool ON lp.router_id = single_pool.router_id
                SET lp.ip_pool_id = single_pool.pool_id
                WHERE lp.ip_pool_id IS NULL
                  AND lp.deleted_at IS NULL
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('layanan_pelanggan', function (Blueprint $table) {
            $table->dropForeign(['ip_pool_id']);
            $table->dropColumn('ip_pool_id');
        });
    }
};
