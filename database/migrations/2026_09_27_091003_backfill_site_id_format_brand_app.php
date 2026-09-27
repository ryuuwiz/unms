<?php

use App\Models\LayananPelanggan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ganti Site ID lama `SITE-XXXXXXXX` ke `[KodePrefix]APP[8 digit]` (ADR-0062). Update lewat
     * query builder agar LayananPelangganObserver (sinkronisasi router) tidak terpicu; setiap
     * penggantian dicatat manual ke activity log layanan. Aman dijalankan ulang: hanya baris
     * berformat lama yang disentuh.
     */
    public function up(): void
    {
        LayananPelanggan::withTrashed()
            ->where('site_id', 'like', 'SITE-%')
            ->with(['pelanggan' => fn ($query) => $query->withTrashed()->select('id', 'no_reg')])
            ->chunkById(500, function ($layanans): void {
                foreach ($layanans as $layanan) {
                    $siteIdBaru = LayananPelanggan::generateSiteId($layanan->pelanggan?->no_reg);

                    DB::table('layanan_pelanggan')->where('id', $layanan->id)->update(['site_id' => $siteIdBaru]);

                    activity('layanan_pelanggan')
                        ->performedOn($layanan)
                        ->withProperties(['site_id_lama' => $layanan->site_id, 'site_id_baru' => $siteIdBaru])
                        ->log("Site ID diganti dari {$layanan->site_id} ke {$siteIdBaru} (ADR-0062)");
                }
            });
    }

    public function down(): void
    {
        // Data backfill; Site ID lama hanya tersimpan di activity log, tidak dipulihkan.
    }
};
