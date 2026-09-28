<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Router Paket (ADR-0063): router yang boleh menjual sebuah paket beserta IP Pool profile-nya.
     * Backfill dari kombinasi paket–router yang sudah dipakai layanan, dengan pool terlama router.
     */
    public function up(): void
    {
        Schema::create('router_paket', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paket_layanan_id')->constrained('paket_layanan')->cascadeOnDelete();
            $table->foreignId('router_id')->constrained('router')->cascadeOnDelete();
            $table->foreignId('ip_pool_id')->constrained('ip_pool')->restrictOnDelete();
            $table->string('deskripsi')->nullable();
            $table->timestamps();

            $table->unique(['paket_layanan_id', 'router_id']);
        });

        $poolTerlama = DB::table('ip_pool')->selectRaw('router_id, MIN(id) as ip_pool_id')->groupBy('router_id')->pluck('ip_pool_id', 'router_id');

        DB::table('layanan_pelanggan')
            ->whereNotNull('router_id')
            ->whereNotNull('paket_layanan_id')
            ->select('paket_layanan_id', 'router_id')
            ->distinct()
            ->get()
            ->filter(fn (object $row): bool => isset($poolTerlama[$row->router_id]))
            ->each(fn (object $row) => DB::table('router_paket')->insert([
                'paket_layanan_id' => $row->paket_layanan_id,
                'router_id' => $row->router_id,
                'ip_pool_id' => $poolTerlama[$row->router_id],
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('router_paket');
    }
};
