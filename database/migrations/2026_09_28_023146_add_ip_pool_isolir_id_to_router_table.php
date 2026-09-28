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
        Schema::table('router', function (Blueprint $table) {
            // IP Pool Isolir (ADR-0063): pool profile ISOLIR di router ini.
            $table->foreignId('ip_pool_isolir_id')->nullable()->constrained('ip_pool')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('router', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ip_pool_isolir_id');
        });
    }
};
