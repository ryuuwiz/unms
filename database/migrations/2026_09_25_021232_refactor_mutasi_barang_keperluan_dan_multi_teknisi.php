<?php

use App\Enums\Barang\TipeMutasiBarang;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keperluan Barang Keluar (teks bebas), banyak teknisi per Barang Keluar, dan kondisi unit
     * sebelum Pengembalian agar Penghapusan Mutasi bisa memulihkannya (ADR-0058).
     */
    public function up(): void
    {
        Schema::table('mutasi_barang', function (Blueprint $table) {
            $table->string('keperluan', 100)->nullable()->after('tipe');
        });

        foreach (TipeMutasiBarang::keluar() as $tipe) {
            DB::table('mutasi_barang')->where('tipe', $tipe->value)->update(['keperluan' => $tipe->label()]);
        }

        Schema::create('mutasi_barang_teknisi', function (Blueprint $table) {
            $table->foreignId('mutasi_barang_id')->constrained('mutasi_barang')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['mutasi_barang_id', 'user_id']);
        });

        DB::table('mutasi_barang_teknisi')->insertUsing(
            ['mutasi_barang_id', 'user_id'],
            DB::table('mutasi_barang')->whereNotNull('teknisi_id')->select('id', 'teknisi_id'),
        );

        Schema::table('mutasi_barang', function (Blueprint $table) {
            $table->dropConstrainedForeignId('teknisi_id');
        });

        Schema::table('mutasi_barang_unit', function (Blueprint $table) {
            $table->foreignId('kondisi_sebelum_id')->nullable()->constrained('kondisi_barang')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mutasi_barang_unit', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kondisi_sebelum_id');
        });

        Schema::table('mutasi_barang', function (Blueprint $table) {
            $table->foreignId('teknisi_id')->nullable()->after('keterangan')->constrained('users')->nullOnDelete();
        });

        DB::table('mutasi_barang_teknisi')->orderBy('mutasi_barang_id')->get()->unique('mutasi_barang_id')
            ->each(fn (object $baris) => DB::table('mutasi_barang')->where('id', $baris->mutasi_barang_id)->update(['teknisi_id' => $baris->user_id]));

        Schema::dropIfExists('mutasi_barang_teknisi');

        Schema::table('mutasi_barang', function (Blueprint $table) {
            $table->dropColumn('keperluan');
        });
    }
};
