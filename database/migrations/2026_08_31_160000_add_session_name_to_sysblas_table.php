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
            $table->string('session_name')->default('default')->after('provider')->comment('Nama session WAHA (multi-session support)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sysblas', function (Blueprint $table) {
            $table->dropColumn('session_name');
        });
    }
};
