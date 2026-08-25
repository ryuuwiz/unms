<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 1. Salin nilai kolom `divisi` lama dari tabel `ticket` ke pivot `ticket_divisi`.
     * 2. Drop kolom `divisi` dari tabel `ticket`.
     * 3. Tambah kolom `is_internal` ke `ticket_histori`.
     */
    public function up(): void
    {
        // 1. Migrate data divisi lama ke pivot table
        DB::table('ticket')
            ->whereNotNull('divisi')
            ->chunkById(200, function ($tickets) {
                $rows = [];
                foreach ($tickets as $ticket) {
                    $rows[] = [
                        'ticket_id' => $ticket->id,
                        'divisi' => $ticket->divisi,
                    ];
                }
                if (! empty($rows)) {
                    DB::table('ticket_divisi')->insertOrIgnore($rows);
                }
            });

        // 2. Drop kolom divisi dari ticket
        Schema::table('ticket', function (Blueprint $table) {
            $table->dropColumn('divisi');
        });

        // 3. Tambah is_internal ke ticket_histori
        Schema::table('ticket_histori', function (Blueprint $table) {
            $table->boolean('is_internal')->default(false)->after('catatan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan kolom divisi ke ticket (isi ulang dari pivot)
        Schema::table('ticket', function (Blueprint $table) {
            $table->string('divisi', 50)->default('teknisi')->after('prioritas');
        });

        // Restore divisi dari pivot (ambil divisi pertama per tiket)
        $pivotRows = DB::table('ticket_divisi')
            ->select('ticket_id', DB::raw('MIN(divisi) as divisi'))
            ->groupBy('ticket_id')
            ->get();

        foreach ($pivotRows as $row) {
            DB::table('ticket')->where('id', $row->ticket_id)->update(['divisi' => $row->divisi]);
        }

        // Drop is_internal dari ticket_histori
        Schema::table('ticket_histori', function (Blueprint $table) {
            $table->dropColumn('is_internal');
        });
    }
};
