<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice', function (Blueprint $table): void {
            $table->date('masa_aktif_mulai')->nullable()->after('tanggal_jatuh_tempo');
            $table->date('masa_aktif_selesai')->nullable()->after('masa_aktif_mulai');
            $table->date('masa_aktif_sebelum')->nullable()->after('masa_aktif_selesai');
        });
    }

    public function down(): void
    {
        Schema::table('invoice', function (Blueprint $table): void {
            $table->dropColumn(['masa_aktif_mulai', 'masa_aktif_selesai', 'masa_aktif_sebelum']);
        });
    }
};
