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
        Schema::table('sysblas', function (Blueprint $table) {
            $table->unsignedInteger('delay_detik')->default(3)->after('limit_per_menit')->comment('Jeda minimal antar pesan dalam detik');
            $table->unsignedInteger('jitter_detik')->default(2)->after('delay_detik')->comment('Variasi jeda acak dalam detik untuk anti-ban');
            $table->boolean('is_typing_simulation')->default(true)->after('jitter_detik')->comment('Simulasi status mengetik pada WAHA');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sysblas', function (Blueprint $table) {
            $table->dropColumn(['delay_detik', 'jitter_detik', 'is_typing_simulation']);
        });
    }
};
