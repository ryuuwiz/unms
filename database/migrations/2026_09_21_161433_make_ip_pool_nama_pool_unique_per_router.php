<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nama pool RouterOS bersifat lokal per router, jadi keunikan cukup per (router_id, nama_pool).
     */
    public function up(): void
    {
        Schema::table('ip_pool', function (Blueprint $table) {
            $table->dropUnique(['nama_pool']);
            $table->unique(['router_id', 'nama_pool']);
        });
    }

    public function down(): void
    {
        Schema::table('ip_pool', function (Blueprint $table) {
            $table->dropUnique(['router_id', 'nama_pool']);
            $table->unique('nama_pool');
        });
    }
};
