<?php

use App\Enums\StatusInvoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Data Reconciliation / Cleanup duplicate pending invoices where a paid invoice exists for same period
        $duplicatePending = DB::table('invoice as i1')
            ->join('invoice as i2', function ($join) {
                $join->on('i1.layanan_pelanggan_id', '=', 'i2.layanan_pelanggan_id')
                    ->on('i1.periode_tagihan', '=', 'i2.periode_tagihan')
                    ->where('i1.id', '!=', DB::raw('i2.id'));
            })
            ->where('i1.status', StatusInvoice::MenungguPembayaran->value)
            ->where('i2.status', StatusInvoice::Lunas->value)
            ->whereNull('i1.deleted_at')
            ->whereNull('i2.deleted_at')
            ->select('i1.id', 'i2.no_invoice')
            ->get();

        foreach ($duplicatePending as $row) {
            DB::table('invoice')
                ->where('id', $row->id)
                ->update([
                    'status' => StatusInvoice::Dibatalkan->value,
                    'keterangan_hapus' => "Dibatalkan sistem: Duplikasi tagihan periode yang sama telah dilunasi pada {$row->no_invoice}.",
                    'updated_at' => now(),
                ]);
        }

        // 2. Add Conditional / Functional Unique Constraint on active invoices
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS unique_active_layanan_periode ON invoice (layanan_pelanggan_id, periode_tagihan) WHERE status != "dibatalkan" AND deleted_at IS NULL');
        } else {
            // MySQL 8.0+ / MariaDB functional index
            DB::statement('CREATE UNIQUE INDEX unique_active_layanan_periode ON invoice ((CASE WHEN status != "dibatalkan" AND deleted_at IS NULL THEN CONCAT(layanan_pelanggan_id, "-", periode_tagihan) ELSE NULL END))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS unique_active_layanan_periode');
        } else {
            DB::statement('DROP INDEX unique_active_layanan_periode ON invoice');
        }
    }
};
