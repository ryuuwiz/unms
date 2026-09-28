<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gerbang Selesai tiket Pencabutan (CONTEXT.md "Pencabutan"): tiket tidak boleh Selesai
     * sebelum PPP Secret dihapus lewat Proses NOC.
     */
    public function up(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->timestamp('secret_dihapus_pada')->nullable()->after('perlu_aktivasi_manual');
            $table->foreignId('secret_dihapus_oleh')->nullable()->after('secret_dihapus_pada')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->dropConstrainedForeignId('secret_dihapus_oleh');
            $table->dropColumn('secret_dihapus_pada');
        });
    }
};
