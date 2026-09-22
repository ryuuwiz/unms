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
        Schema::table('layanan_pelanggan', function (Blueprint $table) {
            $table->foreignId('router_id')->nullable()->change();
            $table->string('ppp_username')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('layanan_pelanggan', function (Blueprint $table) {
            $table->foreignId('router_id')->nullable(false)->change();
            $table->string('ppp_username')->nullable(false)->change();
        });
    }
};
