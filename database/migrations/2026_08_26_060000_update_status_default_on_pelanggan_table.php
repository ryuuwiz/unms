<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pelanggan', function (Blueprint $table) {
            $table->string('status')->default('belum_terpasang')->change();
        });

        DB::table('pelanggan')->where('status', 'prospek')->update(['status' => 'belum_terpasang']);
        DB::table('pelanggan')->where('status', 'tidak_aktif')->update(['status' => 'off']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pelanggan', function (Blueprint $table) {
            $table->string('status')->default('prospek')->change();
        });
    }
};
